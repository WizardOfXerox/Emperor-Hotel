<?php

declare(strict_types=1);

class Payment
{
    private const PAYMENT_METHODS = ['Cash', 'Credit Card', 'Debit Card', 'Bank Transfer', 'Online Payment', 'Other'];
    private const CURRENCIES = ['PHP', 'USD', 'EUR'];
    private const PAYMENT_STATUSES = ['Pending', 'Confirmed', 'Failed', 'Refunded'];

    public function __construct(private PDO $db)
    {
    }

    public static function methods(): array
    {
        return self::PAYMENT_METHODS;
    }

    public static function currencies(): array
    {
        return self::CURRENCIES;
    }

    public static function statuses(): array
    {
        return self::PAYMENT_STATUSES;
    }

    public static function simulatedReference(int $reservationId): string
    {
        return self::generatedReference($reservationId, true);
    }

    public static function generatedReference(int $reservationId, bool $isSimulated = false): string
    {
        $prefix = $isSimulated ? 'SIM' : 'PAY';
        $reservationPart = str_pad((string) $reservationId, 5, '0', STR_PAD_LEFT);
        $randomPart = strtoupper(bin2hex(random_bytes(2)));

        return "{$prefix}-{$reservationPart}-" . date('YmdHis') . "-{$randomPart}";
    }

    public function create(array $data): bool
    {
        return $this->createAndGetId($data) > 0;
    }

    public function createAndGetId(array $data): int
    {
        $reservationId = (int) ($data['reservation_id'] ?? 0);

        $this->assertReservationExists($reservationId);

        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $isSimulated = (bool) ($data['is_simulated'] ?? false);
        $paymentMethod = (string) ($data['payment_method'] ?? 'Cash');
        $currency = (string) ($data['currency'] ?? 'PHP');
        $paymentStatus = (string) ($data['payment_status'] ?? ($isSimulated ? 'Pending' : 'Confirmed'));

        if ($isSimulated) {
            $paymentStatus = 'Pending';
        }

        $transactionReference = self::generatedReference($reservationId, $isSimulated);

        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw new RuntimeException('Please choose a valid payment method.');
        }

        if (!in_array($currency, self::CURRENCIES, true)) {
            throw new RuntimeException('Please choose a valid currency.');
        }

        if (!in_array($paymentStatus, self::PAYMENT_STATUSES, true)) {
            throw new RuntimeException('Please choose a valid payment status.');
        }

        $this->assertPaymentWillNotOverpay($reservationId, $amount, $paymentStatus);

        $statement = $this->db->prepare(
            'INSERT INTO payments (reservation_id, amount, payment_method, currency, payment_status, transaction_reference, notes)
             VALUES (:reservation_id, :amount, :payment_method, :currency, :payment_status, :transaction_reference, :notes)'
        );

        $saved = $statement->execute([
            'reservation_id' => $reservationId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'currency' => $currency,
            'payment_status' => $paymentStatus,
            'transaction_reference' => $transactionReference,
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);

        return $saved ? (int) $this->db->lastInsertId() : 0;
    }

    public function all(): array
    {
        $statement = $this->db->query(
            "SELECT p.*, r.reservation_id, r.total_amount, g.first_name, g.last_name, rm.room_number
             FROM payments p
             INNER JOIN reservations r ON r.reservation_id = p.reservation_id
             INNER JOIN guests g ON g.guest_id = r.guest_id
             INNER JOIN rooms rm ON rm.room_id = r.room_id
             ORDER BY p.payment_date DESC"
        );

        return $statement->fetchAll();
    }

    public function updateStatus(int $paymentId, string $paymentStatus): bool
    {
        if ($paymentId <= 0) {
            throw new RuntimeException('Please choose a valid transaction.');
        }

        $payment = $this->find($paymentId);

        if (!$payment) {
            throw new RuntimeException('Transaction not found.');
        }

        return $this->updateReview($paymentId, (float) $payment['amount'], $paymentStatus);
    }

    public function updateReview(int $paymentId, float $amount, string $paymentStatus): bool
    {
        if ($paymentId <= 0) {
            throw new RuntimeException('Please choose a valid transaction.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        if (!in_array($paymentStatus, self::PAYMENT_STATUSES, true)) {
            throw new RuntimeException('Please choose a valid payment status.');
        }

        $payment = $this->find($paymentId);

        if (!$payment) {
            throw new RuntimeException('Transaction not found.');
        }

        $this->assertPaymentWillNotOverpay((int) $payment['reservation_id'], $amount, $paymentStatus, $paymentId);

        $statement = $this->db->prepare(
            'UPDATE payments
             SET amount = :amount, payment_status = :payment_status
             WHERE payment_id = :payment_id'
        );
        $statement->execute([
            'amount' => $amount,
            'payment_status' => $paymentStatus,
            'payment_id' => $paymentId,
        ]);

        return true;
    }

    public function find(int $paymentId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM payments WHERE payment_id = :payment_id LIMIT 1');
        $statement->execute(['payment_id' => $paymentId]);
        $payment = $statement->fetch();

        return $payment ?: null;
    }

    public function forReservation(int $reservationId): array
    {
        $this->assertReservationExists($reservationId);

        $statement = $this->db->prepare(
            'SELECT *
             FROM payments
             WHERE reservation_id = :reservation_id
             ORDER BY payment_date DESC'
        );
        $statement->execute(['reservation_id' => $reservationId]);

        return $statement->fetchAll();
    }

    public function totalsByReservation(): array
    {
        $statement = $this->db->query(
            "SELECT reservation_id,
                    COALESCE(SUM(amount), 0) AS logged_amount,
                    COALESCE(SUM(CASE WHEN payment_status = 'Confirmed' THEN amount ELSE 0 END), 0) AS confirmed_amount,
                    COALESCE(SUM(CASE WHEN payment_status = 'Pending' THEN amount ELSE 0 END), 0) AS pending_amount
             FROM payments
             GROUP BY reservation_id"
        );

        $totals = [];

        foreach ($statement->fetchAll() as $row) {
            $totals[(int) $row['reservation_id']] = [
                'logged_amount' => (float) $row['logged_amount'],
                'confirmed_amount' => (float) $row['confirmed_amount'],
                'pending_amount' => (float) $row['pending_amount'],
            ];
        }

        return $totals;
    }

    public function totalsForReservation(int $reservationId, ?int $excludePaymentId = null): array
    {
        $this->assertReservationExists($reservationId);

        $reservationStatement = $this->db->prepare('SELECT total_amount FROM reservations WHERE reservation_id = :reservation_id');
        $reservationStatement->execute(['reservation_id' => $reservationId]);
        $reservationTotal = (float) $reservationStatement->fetchColumn();

        $sql = "SELECT
                    COALESCE(SUM(amount), 0) AS logged_amount,
                    COALESCE(SUM(CASE WHEN payment_status = 'Confirmed' THEN amount ELSE 0 END), 0) AS confirmed_amount,
                    COALESCE(SUM(CASE WHEN payment_status = 'Pending' THEN amount ELSE 0 END), 0) AS pending_amount
                FROM payments
                WHERE reservation_id = :reservation_id";
        $params = ['reservation_id' => $reservationId];

        if ($excludePaymentId !== null) {
            $sql .= ' AND payment_id != :payment_id';
            $params['payment_id'] = $excludePaymentId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch() ?: [];

        $confirmedAmount = (float) ($row['confirmed_amount'] ?? 0);
        $pendingAmount = (float) ($row['pending_amount'] ?? 0);

        return [
            'reservation_total' => $reservationTotal,
            'logged_amount' => (float) ($row['logged_amount'] ?? 0),
            'confirmed_amount' => $confirmedAmount,
            'pending_amount' => $pendingAmount,
            'balance_due' => max(0, $reservationTotal - $confirmedAmount),
            'active_balance_due' => max(0, $reservationTotal - $confirmedAmount - $pendingAmount),
        ];
    }

    public function recent(int $limit = 5): array
    {
        $statement = $this->db->prepare(
            "SELECT p.*, g.first_name, g.last_name
             FROM payments p
             INNER JOIN reservations r ON r.reservation_id = p.reservation_id
             INNER JOIN guests g ON g.guest_id = r.guest_id
             ORDER BY p.payment_date DESC
             LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function revenueThisMonth(): float
    {
        return (float) $this->db->query(
            "SELECT COALESCE(SUM(amount), 0)
             FROM payments
             WHERE payment_status = 'Confirmed'
               AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')"
        )->fetchColumn();
    }

    public function summaryByStatus(): array
    {
        $statement = $this->db->query(
            "SELECT payment_status, COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount
             FROM payments
             GROUP BY payment_status
             ORDER BY payment_status ASC"
        );

        return $statement->fetchAll();
    }

    public function failedPayments(int $limit = 5): array
    {
        $statement = $this->db->prepare(
            "SELECT p.*, g.first_name, g.last_name, rm.room_number
             FROM payments p
             INNER JOIN reservations r ON r.reservation_id = p.reservation_id
             INNER JOIN guests g ON g.guest_id = r.guest_id
             INNER JOIN rooms rm ON rm.room_id = r.room_id
             WHERE p.payment_status = 'Failed'
             ORDER BY p.payment_date DESC
             LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function revenueReport(string $startDate, string $endDate): array
    {
        $this->validateReportDateRange($startDate, $endDate);

        $totalStatement = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM payments
             WHERE payment_status = 'Confirmed'
               AND DATE(payment_date) BETWEEN :start_date AND :end_date"
        );
        $totalStatement->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $typeStatement = $this->db->prepare(
            "SELECT
                rm.room_type,
                COUNT(p.payment_id) AS payment_count,
                COALESCE(SUM(p.amount), 0) AS confirmed_revenue
             FROM rooms rm
             LEFT JOIN reservations r ON r.room_id = rm.room_id
             LEFT JOIN payments p
                ON p.reservation_id = r.reservation_id
               AND p.payment_status = 'Confirmed'
               AND DATE(p.payment_date) BETWEEN :start_date AND :end_date
             GROUP BY rm.room_type
             ORDER BY MIN(rm.floor), rm.room_type"
        );
        $typeStatement->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $methodStatement = $this->db->prepare(
            "SELECT
                payment_method,
                COUNT(*) AS payment_count,
                COALESCE(SUM(amount), 0) AS confirmed_revenue
             FROM payments
             WHERE payment_status = 'Confirmed'
               AND DATE(payment_date) BETWEEN :start_date AND :end_date
             GROUP BY payment_method
             ORDER BY confirmed_revenue DESC, payment_method ASC"
        );
        $methodStatement->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return [
            'total_revenue' => (float) $totalStatement->fetchColumn(),
            'by_room_type' => $typeStatement->fetchAll(),
            'by_payment_method' => $methodStatement->fetchAll(),
        ];
    }

    private function assertReservationExists(int $reservationId): void
    {
        if ($reservationId <= 0) {
            throw new RuntimeException('Please choose a reservation before saving a payment.');
        }

        $statement = $this->db->prepare('SELECT COUNT(*) FROM reservations WHERE reservation_id = :reservation_id');
        $statement->execute(['reservation_id' => $reservationId]);

        if ((int) $statement->fetchColumn() === 0) {
            throw new RuntimeException('The selected reservation does not exist. Please refresh the page and choose a valid reservation.');
        }
    }

    private function validateReportDateRange(string $startDate, string $endDate): void
    {
        if ($startDate === '' || $endDate === '') {
            throw new RuntimeException('Report start and end dates are required.');
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);

        if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
            throw new RuntimeException('Please enter valid report dates.');
        }

        if ($end < $start) {
            throw new RuntimeException('Report end date must be the same as or after the start date.');
        }
    }

    private function assertPaymentWillNotOverpay(int $reservationId, float $amount, string $paymentStatus, ?int $excludePaymentId = null): void
    {
        if (!in_array($paymentStatus, ['Pending', 'Confirmed'], true)) {
            return;
        }

        $totals = $this->totalsForReservation($reservationId, $excludePaymentId);
        $activeAmount = (float) $totals['confirmed_amount'] + (float) $totals['pending_amount'] + $amount;

        if ($activeAmount > (float) $totals['reservation_total'] + 0.01) {
            $remaining = max(0, (float) $totals['active_balance_due']);

            throw new RuntimeException('This transaction would overpay the reservation. Remaining payable balance is PHP ' . number_format($remaining, 2) . '.');
        }
    }
}
