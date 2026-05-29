<?php

require_once __DIR__ . '/../core/Cryptography.php';

class User {
    private PDO $db;

    public int $id;
    public string $username;
    private ?string $activeMasterKey = null;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function register(string $username, string $plainPassword): bool {
        try {
            $stmt = $this->db->prepare("INSERT INTO users (username, password, master_key) VALUES (?, ?, ?)");

            $passwordHash = password_hash($plainPassword, PASSWORD_ARGON2ID); // Using Argon2 for modern security

            $rawMasterKey = Cryptography::generateMasterKey();
            $encryptedMasterKey = Cryptography::encrypt($rawMasterKey, $plainPassword);

            return $stmt->execute([$username, $passwordHash, $encryptedMasterKey]);
        } catch (PDOException $e) {
            return false; // Typically duplicate username
        }
    }

    public function login(string $username, string $plainPassword): bool {
        $stmt = $this->db->prepare("SELECT id, password, master_key FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $userRow = $stmt->fetch();

        if ($userRow && password_verify($plainPassword, $userRow['password'])) {
            $this->id = $userRow['id'];
            $this->username = $username;
            // Temporarily hold decrypted master key in object memory
            $this->activeMasterKey = Cryptography::decrypt($userRow['master_key'], $plainPassword);
            return true;
        }
        return false;
    }

    /**
     * Requirement 6: Recode the KEY when changing the login password.
     */
    public function updatePassword(string $oldPassword, string $newPassword): bool {
        // 1. Verify old password first to ensure authorization and retrieve the decrypted master key
        $stmt = $this->db->prepare("SELECT password, master_key FROM users WHERE id = ?");
        $stmt->execute([$this->id]);
        $userRow = $stmt->fetch();

        if (!$userRow || !password_verify($oldPassword, $userRow['password'])) {
            return false;
        }

        // 2. Decrypt the Master Key with the OLD password
        $decryptedMasterKey = Cryptography::decrypt($userRow['master_key'], $oldPassword);

        // 3. Re-encrypt the exact same Master Key with the NEW password
        $newEncryptedMasterKey = Cryptography::encrypt($decryptedMasterKey, $newPassword);
        $newPasswordHash = password_hash($newPassword, PASSWORD_ARGON2ID);

        // 4. Update the database
        $updateStmt = $this->db->prepare("UPDATE users SET password = ?, master_key = ? WHERE id = ?");
        return $updateStmt->execute([$newPasswordHash, $newEncryptedMasterKey, $this->id]);
    }

    public function getActiveMasterKey(): ?string {
        return $this->activeMasterKey;
    }
}