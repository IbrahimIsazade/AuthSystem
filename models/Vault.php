<?php

require_once __DIR__ . '/../core/Cryptography.php';

class Vault {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function saveEntry(int $userId, string $serviceName, string $plainSecret, string $masterKey): bool {
        $encryptedPayload = Crypto::encrypt($plainSecret, $masterKey);

        $stmt = $this->db->prepare("INSERT INTO vault (user_id, service_name, secret_payload) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $serviceName, $encryptedPayload]);
    }

    public function getUserEntries(int $userId, string $masterKey): array {
        $stmt = $this->db->prepare("SELECT id, service_name, secret_payload, created_at FROM vault WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);

        $entries = [];
        while ($row = $stmt->fetch()) {
            $row['decrypted_secret'] = Crypto::decrypt($row['secret_payload'], $masterKey);
            $entries[] = $row;
        }
        return $entries;
    }
}