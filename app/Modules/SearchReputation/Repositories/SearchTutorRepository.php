<?php

declare(strict_types=1);

namespace App\Modules\SearchReputation\Repositories;

use App\Modules\SearchReputation\DTOs\SearchFiltersDTO;
use App\Shared\DTOs\TutorProfileDTO;
use App\Shared\DTOs\UserDTO;
use PDO;

class SearchTutorRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return TutorProfileDTO[]
     */
    public function search(SearchFiltersDTO $filters): array
    {
        $sql = "
            SELECT 
                tp.id, tp.headline, tp.bio, tp.hourly_rate, tp.rating_avg, 
                tp.reviews_count, tp.modality, tp.city, tp.subjects,
                u.id AS user_id, u.name, u.email, u.role, u.avatar_url, u.phone
            FROM tutor_profiles tp
            INNER JOIN users u ON u.id = tp.user_id
            WHERE 1=1
        ";
        
        $params = [];

        if ($filters->query) {
            $sql .= " AND (u.name LIKE :query OR tp.headline LIKE :query OR tp.subjects LIKE :query)";
            $params[':query'] = '%' . $filters->query . '%';
        }

        if ($filters->modality) {
            $sql .= " AND tp.modality = :modality";
            $params[':modality'] = $filters->modality->value;
        }

        if ($filters->minPrice !== null) {
            $sql .= " AND tp.hourly_rate >= :min_price";
            $params[':min_price'] = $filters->minPrice;
        }

        if ($filters->maxPrice !== null) {
            $sql .= " AND tp.hourly_rate <= :max_price";
            $params[':max_price'] = $filters->maxPrice;
        }

        if ($filters->city) {
            $sql .= " AND tp.city = :city";
            $params[':city'] = $filters->city;
        }

        if ($filters->subject) {
            $sql .= " AND tp.subjects LIKE :subject";
            $params[':subject'] = '%' . $filters->subject . '%';
        }

        if ($filters->minRating !== null) {
            $sql .= " AND tp.rating_avg >= :min_rating";
            $params[':min_rating'] = $filters->minRating;
        }

        // Ordenamiento dinámico seguro
        $sql .= match($filters->sortBy) {
            'price_asc' => " ORDER BY tp.hourly_rate ASC",
            'price_desc' => " ORDER BY tp.hourly_rate DESC",
            default => " ORDER BY tp.rating_avg DESC, tp.reviews_count DESC",
        };

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): TutorProfileDTO {
            $userDto = UserDTO::fromArray([
                'id' => $row['user_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'avatar_url' => $row['avatar_url'],
                'phone' => $row['phone'],
            ]);

            return TutorProfileDTO::fromArray([
                'id' => $row['id'],
                'headline' => $row['headline'],
                'bio' => $row['bio'],
                'hourly_rate' => $row['hourly_rate'],
                'rating_avg' => $row['rating_avg'],
                'reviews_count' => $row['reviews_count'],
                'modality' => $row['modality'],
                'city' => $row['city'],
                'subjects' => is_string($row['subjects']) ? json_decode($row['subjects'], true) : ($row['subjects'] ?? []),
            ], $userDto);
        }, $rows);
    }
}