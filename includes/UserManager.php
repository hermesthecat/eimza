<?php

class UserManager {
    private $db;
    private $logger;

    public function __construct($db, $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Hash a password using bcrypt
     * @param string $password The password to hash
     * @return string The hashed password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify a password against a hash
     * @param string $password The password to verify
     * @param string $hash The hash to verify against
     * @return bool True if the password matches the hash
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Create a new user
     * @param string $username The username
     * @param string $password The plain text password (will be hashed)
     * @param string $fullName The user's full name
     * @param string $email The user's email
     * @param string $role The user's role (admin or user)
     * @return bool|array False on failure, user data on success
     */
    public function createUser($username, $password, $fullName, $email, $role = 'user') {
        try {
            $hashedPassword = $this->hashPassword($password);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password_hash, full_name, email, role)
                VALUES (:username, :password_hash, :full_name, :email, :role)
            ");

            $stmt->execute([
                ':username' => $username,
                ':password_hash' => $hashedPassword,
                ':full_name' => $fullName,
                ':email' => $email,
                ':role' => $role
            ]);

            $userId = $this->db->lastInsertId();
            $this->logger->info("User created successfully", ['userId' => $userId, 'username' => $username]);

            return $this->getUserById($userId);
        } catch (PDOException $e) {
            $this->logger->error("Error creating user", [
                'error' => $e->getMessage(),
                'username' => $username
            ]);
            return false;
        }
    }

    /**
     * Get a user by their username
     * @param string $username The username to look up
     * @return bool|array False if not found, user data if found
     */
    public function getUserByUsername($username) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, password_hash, full_name, email, role, tckn, last_login, created_at
                FROM users
                WHERE username = :username
            ");

            $stmt->execute([':username' => $username]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error("Error getting user by username", [
                'error' => $e->getMessage(),
                'username' => $username
            ]);
            return false;
        }
    }

    /**
     * Get a user by their ID
     * @param int $id The user ID to look up
     * @return bool|array False if not found, user data if found
     */
    public function getUserById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, password_hash, full_name, email, role, tckn, last_login, created_at
                FROM users
                WHERE id = :id
            ");

            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error("Error getting user by ID", [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return false;
        }
    }

    /**
     * Get all users, optionally filtered by role
     * @param string|null $role Optional role to filter by (admin, user)
     * @return array Array of user data or empty array on failure
     */
    public function getAllUsers($role = null) {
        try {
            $sql = "
                SELECT id, username, full_name, email, role, tckn
                FROM users
                WHERE 1=1
            ";
            $params = [];

            if ($role !== null) {
                $sql .= " AND role = :role";
                $params[':role'] = $role;
            }

            $sql .= " ORDER BY full_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error("Error getting all users", [
                'error' => $e->getMessage(),
                'role_filter' => $role
            ]);
            return [];
        }
    }

    /**
     * Update a user's last login time
     * @param int $userId The ID of the user to update
     * @return bool True on success, false on failure
     */
    public function updateLastLogin($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users
                SET last_login = NOW()
                WHERE id = :id
            ");

            return $stmt->execute([':id' => $userId]);
        } catch (PDOException $e) {
            $this->logger->error("Error updating last login", [
                'error' => $e->getMessage(),
                'userId' => $userId
            ]);
            return false;
        }
    }

    /**
     * Update a user's password
     * @param int $userId The ID of the user
     * @param string $newPassword The new password (will be hashed)
     * @return bool True on success, false on failure
     */
    public function updatePassword($userId, $newPassword) {
        try {
            $hashedPassword = $this->hashPassword($newPassword);
            
            $stmt = $this->db->prepare("
                UPDATE users
                SET password_hash = :password_hash
                WHERE id = :id
            ");

            $success = $stmt->execute([
                ':password_hash' => $hashedPassword,
                ':id' => $userId
            ]);

            if ($success) {
                $this->logger->info("Password updated successfully", ['userId' => $userId]);
            }

            return $success;
        } catch (PDOException $e) {
            $this->logger->error("Error updating password", [
                'error' => $e->getMessage(),
                'userId' => $userId
            ]);
            return false;
        }
    }
}