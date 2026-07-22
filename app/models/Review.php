<?php

declare(strict_types=1);

class Review
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): bool
    {
        $reservationId = (int) ($data['reservation_id'] ?? 0);
        $userId = (int) ($data['user_id'] ?? 0);
        $roomId = (int) ($data['room_id'] ?? 0);
        $rating = (int) ($data['rating'] ?? 0);
        $comment = trim((string) ($data['comment'] ?? ''));

        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('Rating must be between 1 and 5 stars.');
        }

        if ($reservationId <= 0 || $userId <= 0 || $roomId <= 0) {
            throw new RuntimeException('Invalid review parameters.');
        }

        // Verify that the user has a valid completed or checked-out stay for this reservation
        $checkStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM reservations 
             WHERE reservation_id = :res_id 
               AND user_id = :user_id 
               AND room_id = :room_id 
               AND status IN ('Checked-out', 'Confirmed')"
        );
        $checkStmt->execute([
            'res_id' => $reservationId,
            'user_id' => $userId,
            'room_id' => $roomId,
        ]);

        if ((int) $checkStmt->fetchColumn() === 0) {
            throw new RuntimeException('You can only review rooms for completed stays.');
        }

        // Check if already reviewed
        $existingStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM room_reviews WHERE reservation_id = :res_id AND user_id = :user_id"
        );
        $existingStmt->execute(['res_id' => $reservationId, 'user_id' => $userId]);

        if ((int) $existingStmt->fetchColumn() > 0) {
            throw new RuntimeException('You have already submitted a review for this stay.');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO room_reviews (reservation_id, user_id, room_id, rating, comment)
             VALUES (:res_id, :user_id, :room_id, :rating, :comment)"
        );

        return $stmt->execute([
            'res_id' => $reservationId,
            'user_id' => $userId,
            'room_id' => $roomId,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    public function reviewsForRoom(int $roomId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.*, u.full_name 
             FROM room_reviews r
             JOIN users u ON r.user_id = u.user_id
             WHERE r.room_id = :room_id
             ORDER BY r.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function reviewsForRoomType(string $roomType, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.*, u.full_name, rm.room_number
             FROM room_reviews r
             JOIN users u ON r.user_id = u.user_id
             JOIN rooms rm ON r.room_id = rm.room_id
             WHERE rm.room_type = :room_type
             ORDER BY r.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':room_type', $roomType);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function averageRatingForRoom(int $roomId): array
    {
        $stmt = $this->db->prepare(
            "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count
             FROM room_reviews
             WHERE room_id = :room_id"
        );
        $stmt->execute(['room_id' => $roomId]);
        $row = $stmt->fetch();

        return [
            'avg_rating' => $row && $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 1) : 5.0,
            'review_count' => (int) ($row['review_count'] ?? 0),
        ];
    }

    public function averageRatingPerRoomType(): array
    {
        $stmt = $this->db->query(
            "SELECT rm.room_type, AVG(r.rating) as avg_rating, COUNT(r.review_id) as review_count
             FROM rooms rm
             LEFT JOIN room_reviews r ON rm.room_id = r.room_id
             GROUP BY rm.room_type"
        );

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['room_type']] = [
                'avg_rating' => $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 1) : 5.0,
                'review_count' => (int) $row['review_count'],
            ];
        }

        return $result;
    }

    public function overallRatingDistribution(): array
    {
        $stmt = $this->db->query(
            "SELECT rating, COUNT(*) as count
             FROM room_reviews
             GROUP BY rating
             ORDER BY rating DESC"
        );

        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($stmt->fetchAll() as $row) {
            $dist[(int) $row['rating']] = (int) $row['count'];
        }

        return $dist;
    }
}
