<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Utils\UUID;

/**
 * Modèle de gestion des documents (pièces jointes polymorphiques)
 */
class Document
{
    // Types MIME autorisés
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf'
    ];

    // Taille maximale (10 Mo)
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;

    // Types d'entités pouvant avoir des documents
    public const DOCUMENTABLE_TYPES = ['user', 'person'];

    // Extensions autorisées
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

    /**
     * Trouve un document par son ID
     */
    public static function findById(string $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT d.*,
                   u.first_name as uploader_first_name,
                   u.last_name as uploader_last_name
            FROM documents d
            INNER JOIN users u ON d.uploaded_by = u.id
            WHERE d.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $document = $stmt->fetch();

        return $document ?: null;
    }

    /**
     * Trouve tous les documents d'une entité
     */
    public static function findByDocumentable(string $type, string $id): array
    {
        if (!in_array($type, self::DOCUMENTABLE_TYPES)) {
            return [];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT d.*,
                   u.first_name as uploader_first_name,
                   u.last_name as uploader_last_name
            FROM documents d
            INNER JOIN users u ON d.uploaded_by = u.id
            WHERE d.documentable_type = :type AND d.documentable_id = :id
            ORDER BY d.created_at DESC
        ');
        $stmt->execute(['type' => $type, 'id' => $id]);

        return $stmt->fetchAll();
    }

    /**
     * Compte les documents d'une entité
     */
    public static function countByDocumentable(string $type, string $id): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM documents
            WHERE documentable_type = :type AND documentable_id = :id
        ');
        $stmt->execute(['type' => $type, 'id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Crée un nouveau document
     */
    public static function create(array $data): string
    {
        $db = Database::getInstance();
        $id = UUID::generate();

        $stmt = $db->prepare('
            INSERT INTO documents (id, documentable_type, documentable_id, filename, original_name, mime_type, size, uploaded_by)
            VALUES (:id, :documentable_type, :documentable_id, :filename, :original_name, :mime_type, :size, :uploaded_by)
        ');

        $stmt->execute([
            'id' => $id,
            'documentable_type' => $data['documentable_type'],
            'documentable_id' => $data['documentable_id'],
            'filename' => $data['filename'],
            'original_name' => $data['original_name'],
            'mime_type' => $data['mime_type'],
            'size' => $data['size'],
            'uploaded_by' => $data['uploaded_by']
        ]);

        return $id;
    }

    /**
     * Supprime un document
     */
    public static function delete(string $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM documents WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Supprime tous les documents d'une entité
     */
    public static function deleteByDocumentable(string $type, string $id): bool
    {
        // Récupérer d'abord les documents pour supprimer les fichiers
        $documents = self::findByDocumentable($type, $id);

        foreach ($documents as $document) {
            $filepath = self::getFilePath($document);
            if ($filepath && file_exists($filepath)) {
                unlink($filepath);
            }
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM documents WHERE documentable_type = :type AND documentable_id = :id');
        return $stmt->execute(['type' => $type, 'id' => $id]);
    }

    /**
     * Génère le chemin du répertoire d'upload
     */
    public static function getUploadDir(): string
    {
        $year = date('Y');
        $month = date('m');
        $basePath = __DIR__ . '/../../uploads/documents/' . $year . '/' . $month;

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        return $basePath;
    }

    /**
     * Génère un nom de fichier unique
     */
    public static function generateFilename(string $extension): string
    {
        return UUID::generate() . '.' . strtolower($extension);
    }

    /**
     * Récupère le chemin complet d'un fichier document
     */
    public static function getFilePath(array $document): ?string
    {
        if (empty($document['created_at']) || empty($document['filename'])) {
            return null;
        }

        try {
            $createdAt = new \DateTime($document['created_at']);
            $year = $createdAt->format('Y');
            $month = $createdAt->format('m');
            return __DIR__ . '/../../uploads/documents/' . $year . '/' . $month . '/' . $document['filename'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Vérifie si un type MIME est autorisé
     */
    public static function isAllowedMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::ALLOWED_MIME_TYPES);
    }

    /**
     * Vérifie si une extension est autorisée
     */
    public static function isAllowedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::ALLOWED_EXTENSIONS);
    }

    /**
     * Vérifie si la taille est autorisée
     */
    public static function isAllowedSize(int $size): bool
    {
        return $size <= self::MAX_FILE_SIZE;
    }

    /**
     * Formate la taille en bytes vers une chaîne lisible
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        } else {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
    }
}
