<?php

declare(strict_types=1);

class ContactMessage
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): int
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($fullName === '' || $email === '' || $message === '') {
            throw new RuntimeException('Full name, email, and message content are required.');
        }

        $userId = isset($data['user_id']) && (int)$data['user_id'] > 0 ? (int)$data['user_id'] : null;
        $phone = trim((string) ($data['phone'] ?? ''));
        $inquiryType = trim((string) ($data['inquiry_type'] ?? 'General Inquiry'));
        $subject = trim((string) ($data['subject'] ?? ''));

        $stmt = $this->db->prepare(
            'INSERT INTO contact_messages (user_id, full_name, email, phone, inquiry_type, subject, message, status)
             VALUES (:user_id, :full_name, :email, :phone, :inquiry_type, :subject, :message, "Unread")'
        );

        $stmt->execute([
            'user_id' => $userId,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'inquiry_type' => $inquiryType !== '' ? $inquiryType : 'General Inquiry',
            'subject' => $subject !== '' ? $subject : null,
            'message' => $message,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function find(int $messageId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contact_messages WHERE message_id = :message_id LIMIT 1');
        $stmt->execute(['message_id' => $messageId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function paginated(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(full_name LIKE :search OR email LIKE :search OR phone LIKE :search OR message LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['Unread', 'Read', 'Replied'], true)) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $inquiryType = trim((string) ($filters['inquiry_type'] ?? ''));
        if ($inquiryType !== '') {
            $conditions[] = 'inquiry_type = :inquiry_type';
            $params['inquiry_type'] = $inquiryType;
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // SQL: Count total matching messages
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM contact_messages {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // SQL: Fetch paginated messages
        $dataSql = "SELECT * FROM contact_messages {$whereSql} ORDER BY created_at DESC, message_id DESC LIMIT :limit OFFSET :offset";
        $dataStmt = $this->db->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue(':' . $k, $v);
        }
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'rows' => $dataStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function markAsRead(int $messageId): bool
    {
        $stmt = $this->db->prepare('UPDATE contact_messages SET status = "Read" WHERE message_id = :message_id AND status = "Unread"');
        return $stmt->execute(['message_id' => $messageId]);
    }

    public function reply(int $messageId, string $replyText): bool
    {
        $replyText = trim($replyText);
        if ($replyText === '') {
            throw new RuntimeException('Reply message content cannot be empty.');
        }

        $stmt = $this->db->prepare(
            'UPDATE contact_messages 
             SET reply_message = :reply_message, replied_at = NOW(), status = "Replied" 
             WHERE message_id = :message_id'
        );

        return $stmt->execute([
            'reply_message' => $replyText,
            'message_id' => $messageId,
        ]);
    }

    public function delete(int $messageId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM contact_messages WHERE message_id = :message_id');
        return $stmt->execute(['message_id' => $messageId]);
    }

    public function countUnread(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'Unread'")->fetchColumn();
    }

    public function statusSummary(): array
    {
        $summary = [
            'unread' => 0,
            'read' => 0,
            'replied' => 0,
            'total' => 0,
        ];

        $rows = $this->db->query("SELECT status, COUNT(*) AS cnt FROM contact_messages GROUP BY status")->fetchAll();
        foreach ($rows as $row) {
            $st = strtolower((string)$row['status']);
            if (isset($summary[$st])) {
                $summary[$st] = (int) $row['cnt'];
            }
            $summary['total'] += (int) $row['cnt'];
        }

        return $summary;
    }
}
