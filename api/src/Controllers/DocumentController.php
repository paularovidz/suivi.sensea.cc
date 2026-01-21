<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\Person;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Utils\Response;

/**
 * Contrôleur pour la gestion des documents
 * - Admin : accès complet (upload, suppression, lecture de tous les documents)
 * - Utilisateur : lecture de ses propres documents et de ceux des personnes assignées
 */
class DocumentController
{
    /**
     * Vérifie si l'utilisateur a accès aux documents d'une entité
     */
    private function canAccessEntity(string $type, string $id, array $currentUser, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        if ($type === 'user') {
            // L'utilisateur peut voir ses propres documents
            return $currentUser['id'] === $id;
        } else {
            // L'utilisateur peut voir les documents des personnes qui lui sont assignées
            return Person::isAssignedToUser($id, $currentUser['id']);
        }
    }

    /**
     * Liste les documents d'une entité
     */
    public function listByEntity(string $type, string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        // Valider le type
        if (!in_array($type, Document::DOCUMENTABLE_TYPES)) {
            Response::validationError(['type' => 'Type invalide']);
        }

        // Vérifier que l'entité existe
        if ($type === 'user') {
            $entity = User::findById($id);
        } else {
            $entity = Person::findById($id);
        }

        if (!$entity) {
            Response::notFound('Entité non trouvée');
        }

        // Vérifier l'accès
        if (!$this->canAccessEntity($type, $id, $currentUser, $isAdmin)) {
            Response::forbidden('Accès non autorisé');
        }

        $documents = Document::findByDocumentable($type, $id);

        Response::success([
            'documents' => $documents,
            'count' => count($documents)
        ]);
    }

    /**
     * Upload un document
     */
    public function upload(string $type, string $id): void
    {
        AuthMiddleware::requireAdmin();

        // Valider le type
        if (!in_array($type, Document::DOCUMENTABLE_TYPES)) {
            Response::validationError(['type' => 'Type invalide']);
        }

        // Vérifier que l'entité existe
        if ($type === 'user') {
            $entity = User::findById($id);
        } else {
            $entity = Person::findById($id);
        }

        if (!$entity) {
            Response::notFound('Entité non trouvée');
        }

        // Vérifier le fichier uploadé
        if (empty($_FILES['file'])) {
            Response::validationError(['file' => 'Fichier requis']);
        }

        $file = $_FILES['file'];

        // Vérifier les erreurs d'upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
                UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté le téléchargement'
            ];
            $message = $errorMessages[$file['error']] ?? 'Erreur lors du téléchargement';
            Response::error($message, 400);
        }

        // Vérifier la taille
        if (!Document::isAllowedSize($file['size'])) {
            Response::validationError(['file' => 'Fichier trop volumineux (max 10 MB)']);
        }

        // Vérifier le type MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!Document::isAllowedMimeType($mimeType)) {
            Response::validationError(['file' => 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WebP, PDF']);
        }

        // Vérifier l'extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!Document::isAllowedExtension($extension)) {
            Response::validationError(['file' => 'Extension de fichier non autorisée']);
        }

        // Générer le nom de fichier et obtenir le chemin de destination
        $filename = Document::generateFilename($extension);
        $uploadDir = Document::getUploadDir();
        $filepath = $uploadDir . '/' . $filename;

        // Déplacer le fichier
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Response::error('Erreur lors de la sauvegarde du fichier', 500);
        }

        // Créer l'enregistrement en base
        $documentId = Document::create([
            'documentable_type' => $type,
            'documentable_id' => $id,
            'filename' => $filename,
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'size' => $file['size'],
            'uploaded_by' => AuthMiddleware::getCurrentUserId()
        ]);

        $document = Document::findById($documentId);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'document_uploaded',
            'document',
            $documentId,
            null,
            [
                'documentable_type' => $type,
                'documentable_id' => $id,
                'filename' => $file['name'],
                'size' => $file['size']
            ]
        );

        Response::success($document, 'Document téléversé avec succès', 201);
    }

    /**
     * Télécharge un document
     */
    public function download(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $document = Document::findById($id);

        if (!$document) {
            Response::notFound('Document non trouvé');
        }

        // Vérifier l'accès via l'entité parente
        if (!$this->canAccessEntity($document['documentable_type'], $document['documentable_id'], $currentUser, $isAdmin)) {
            Response::forbidden('Accès non autorisé');
        }

        // Reconstruire le chemin du fichier
        $filepath = Document::getFilePath($document);

        if (!$filepath || !file_exists($filepath)) {
            Response::notFound('Fichier non trouvé sur le disque');
        }

        // Envoyer le fichier
        header('Content-Type: ' . $document['mime_type']);
        header('Content-Disposition: attachment; filename="' . addslashes($document['original_name']) . '"');
        header('Content-Length: ' . $document['size']);
        header('Cache-Control: no-cache, must-revalidate');

        readfile($filepath);
        exit;
    }

    /**
     * Affiche un document (inline)
     */
    public function view(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $document = Document::findById($id);

        if (!$document) {
            Response::notFound('Document non trouvé');
        }

        // Vérifier l'accès via l'entité parente
        if (!$this->canAccessEntity($document['documentable_type'], $document['documentable_id'], $currentUser, $isAdmin)) {
            Response::forbidden('Accès non autorisé');
        }

        $filepath = Document::getFilePath($document);

        if (!$filepath || !file_exists($filepath)) {
            Response::notFound('Fichier non trouvé sur le disque');
        }

        // Envoyer le fichier pour affichage inline (images et PDF)
        header('Content-Type: ' . $document['mime_type']);
        header('Content-Disposition: inline; filename="' . addslashes($document['original_name']) . '"');
        header('Content-Length: ' . $document['size']);
        header('Cache-Control: public, max-age=86400');

        readfile($filepath);
        exit;
    }

    /**
     * Supprime un document
     */
    public function destroy(string $id): void
    {
        AuthMiddleware::requireAdmin();

        $document = Document::findById($id);

        if (!$document) {
            Response::notFound('Document non trouvé');
        }

        // Supprimer le fichier du disque
        $filepath = Document::getFilePath($document);

        if ($filepath && file_exists($filepath)) {
            unlink($filepath);
        }

        // Supprimer l'enregistrement en base
        Document::delete($id);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'document_deleted',
            'document',
            $id,
            [
                'documentable_type' => $document['documentable_type'],
                'documentable_id' => $document['documentable_id'],
                'filename' => $document['original_name']
            ],
            null
        );

        Response::success(null, 'Document supprimé');
    }
}
