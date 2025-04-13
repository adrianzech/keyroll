<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Handles the business logic for updating user account settings.
 * Encapsulates password hashing, change detection, persistence, and security token updates.
 */
class UserSettingsUpdater
{
    // The name of the firewall configured in security.yaml, needed for updating the security token.
    private const FIREWALL_NAME = 'main';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Processes the user settings form, updates the user entity, persists changes,
     * and updates the security token if the email (user identifier) changes.
     *
     * @param FormInterface<User> $form the submitted and valid user account form
     * @param User                $user the user entity being modified
     *
     * @return array{changes_made: bool, email_changed: bool, password_changed: bool, error: bool} An array indicating the outcome:
     *                                                                                             - changes_made: True if any data was actually changed and persisted.
     *                                                                                             - email_changed: True if the email was changed and persisted.
     *                                                                                             - password_changed: True if the password was changed and persisted.
     *                                                                                             - error: True if an exception occurred during persistence.
     */
    public function updateSettings(FormInterface $form, User $user): array
    {
        $originalEmail = $user->getEmail();
        $passwordChanged = $this->handlePasswordUpdate($form, $user);

        $unitOfWork = $this->entityManager->getUnitOfWork();
        // Ensure Doctrine computes changes based on form modifications *before* checking.
        $unitOfWork->computeChangeSets();
        $emailChanged = $this->checkEmailChange($user, $originalEmail, $unitOfWork);

        $changesMade = $emailChanged || $passwordChanged;
        $errorOccurred = false;

        if ($changesMade) {
            try {
                $this->entityManager->flush();
                // Update the security token only after successful persistence if email changed.
                if ($emailChanged) {
                    $this->updateSecurityToken($user);
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to save user settings: ' . $e->getMessage(), ['exception' => $e]);
                $errorOccurred = true;
                // Reset flags as the save operation failed.
                $changesMade = false;
                $emailChanged = false;
                $passwordChanged = false;
            }
        }

        return [
            'changes_made' => $changesMade,
            'email_changed' => $emailChanged,
            'password_changed' => $passwordChanged,
            'error' => $errorOccurred,
        ];
    }

    /**
     * Checks if the User's email address has been modified using Doctrine's Unit of Work.
     *
     * @param User       $user          the user entity
     * @param string     $originalEmail the email address before potential form modifications
     * @param UnitOfWork $unitOfWork    doctrine's Unit of Work service
     */
    private function checkEmailChange(User $user, string $originalEmail, UnitOfWork $unitOfWork): bool
    {
        // Check if the entity is even managed and scheduled for update
        if (!$unitOfWork->isScheduledForUpdate($user)) {
            return false;
        }
        $changeSet = $unitOfWork->getEntityChangeSet($user);

        // Check if the 'email' field is part of the changeset
        if (!isset($changeSet['email'])) {
            return false;
        }

        // Compare the new value in the changeset to the original email
        $newEmail = $changeSet['email'][1]; // Index 1 holds the new value

        return is_string($newEmail) && $newEmail !== $originalEmail;
    }

    /**
     * Hashes and sets the new password on the user entity if a plain password was provided in the form.
     *
     * @param FormInterface<User> $form the user account form
     * @param User                $user the user entity to update
     *
     * @return bool true if the password was updated, false otherwise
     */
    private function handlePasswordUpdate(FormInterface $form, User $user): bool
    {
        // Access the 'first' field of the 'plainPassword' repeated field
        $plainPassword = $form->get('plainPassword')->get('first')->getData();

        // Only proceed if a non-empty password string was submitted
        if (!is_string($plainPassword) || $plainPassword === '') {
            return false;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);

        // Avoid unnecessary updates if the hash is identical (e.g., user re-entered the same password)
        if ($hashedPassword === $user->getPassword()) {
            return false;
        }

        $user->setPassword($hashedPassword);

        return true;
    }

    /**
     * Manually updates the security token in the session.
     * This is necessary when the user identifier (like email/username) changes
     * to prevent the user from being logged out or encountering permission issues.
     */
    private function updateSecurityToken(User $user): void
    {
        // Create a new token with the updated user object.
        $token = new UsernamePasswordToken(
            $user,                      // The updated user entity
            self::FIREWALL_NAME,        // Authenticator name
            $user->getRoles()           // User roles
        );
        // Replace the existing token in the storage with the new one.
        $this->tokenStorage->setToken($token);
    }
}
