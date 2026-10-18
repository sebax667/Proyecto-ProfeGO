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
        if (($filters->subjectId !== null && $filters->subjectId <= 0)
            || ($filters->levelId !== null && $filters->levelId <= 0)) {
            throw new \InvalidArgumentException('Los filtros de materia y nivel deben ser enteros positivos.');
        }
        if ($filters->subject !== null
            && (trim($filters->subject) === '' || mb_strlen($filters->subject, 'UTF-8') > 100)) {
            throw new \InvalidArgumentException('El filtro de materia debe contener entre 1 y 100 caracteres.');
        }

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

        if ($filters->subject !== null || $filters->subjectId !== null || $filters->levelId !== null) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM tutor_subjects ts
                INNER JOIN subjects s ON s.id = ts.subject_id
                WHERE ts.tutor_id = tp.id";
            if ($filters->subjectId !== null) {
                $sql .= ' AND ts.subject_id = :subject_id';
                $params[':subject_id'] = $filters->subjectId;
            } elseif ($filters->subject !== null) {
                $sql .= ' AND s.name = :subject COLLATE NOCASE';
                $params[':subject'] = $filters->subject;
            }
            if ($filters->levelId !== null) {
                $sql .= ' AND ts.level_id = :level_id';
                $params[':level_id'] = $filters->levelId;
            }
            $sql .= ')';
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

        return array_map(fn (array $row): TutorProfileDTO => $this->mapRow($row), $rows);
    }

    public function findById(int $id): ?TutorProfileDTO
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT tp.id, tp.headline, tp.bio, tp.hourly_rate, tp.rating_avg,
                    tp.reviews_count, tp.modality, tp.city, tp.subjects,
                    u.id AS user_id, u.name, u.email, u.role, u.avatar_url, u.phone
             FROM tutor_profiles tp
             INNER JOIN users u ON u.id = tp.user_id
             WHERE tp.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapRow($row);
    }

    private function mapRow(array $row): TutorProfileDTO
    {
        $userDto = UserDTO::fromArray([
            'id' => $row['user_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'avatar_url' => $row['avatar_url'],
            'phone' => $row['phone'],
        ]);

        $subjects = is_string($row['subjects'] ?? null)
            ? json_decode($row['subjects'], true)
            : ($row['subjects'] ?? []);

        return TutorProfileDTO::fromArray([
            'id' => $row['id'],
            'headline' => $row['headline'],
            'bio' => $row['bio'],
            'hourly_rate' => $row['hourly_rate'],
            'rating_avg' => $row['rating_avg'],
            'reviews_count' => $row['reviews_count'],
            'modality' => $row['modality'],
            'city' => $row['city'],
            'subjects' => is_array($subjects) ? $subjects : [],
        ], $userDto);
    }
}