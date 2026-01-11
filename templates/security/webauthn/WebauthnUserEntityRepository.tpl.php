<?= "<?php\n" ?>

namespace <?= $namespace ?>;

<?= $use_statements; ?>

final readonly class <?= $class_name ?> implements PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(
        private <?= $user_repository_class_name ?> $userRepository,
    ) {
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $user = $this->userRepository->findOneBy(['<?= $user_name_field ?>' => $username]);
        if (null === $user) {
            return null;
        }

        return $this->createUserEntity($user);
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $user = $this->userRepository->find($userHandle);
        if (null === $user) {
            return null;
        }

        return $this->createUserEntity($user);
    }

    private function createUserEntity(<?= $user_entity_class_name ?> $user): PublicKeyCredentialUserEntity
    {
        // Convert User ID to string for user handle
        $id = $user->getId();
        $userHandle = $id instanceof Ulid ? $id->toRfc4122() : (string) $id;

        return new PublicKeyCredentialUserEntity(
            $user->getUserIdentifier(),
            $userHandle,
            $user->getUserIdentifier(),
        );
    }
}
