<?php

declare(strict_types=1);

class Payment
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): bool
    {
        if ((float) $data['amount'] <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $statement = $this->db->prepare(
            'INSERT INTO payments (reservation_id, amount, payment_method, currency, payment_status, transaction_reference, notes)
             VALUES (:reservation_id, :amount, :payment_method, :currency, :payment_status, :transaction_reference, :notes)'
        );

        return $statement->execute([
            'reservation_id' => (int) $data['reservation_id'],
            'amount' => (float) $data['amount'],
            'payment_method' => $data['payment_method'],
            'currency' => $data['currency'],
            'payment_status' => $data['payment_status'],
            'transaction_reference' => trim((string) ($data['transaction_reference'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);
    }

    public function all(): array
    {
        $statement = $this->db->query(
            "SELECT p.*, r.reservation_id, g.first_name, g.last_name, rm.room_number
             FROM payments p
             INNER JOIN reservations r ON r.reservation_id = p.reservation_id
             INNER JOIN guests g ON g.guest_id = r.guest_id
             INNER JOIN rooms rm ON rm.room_id = r.room_id
             ORDER BY p.payment_date DESC"
        );

        return $statement->fetchAll();
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
}
