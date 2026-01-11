<?= "<?php\n" ?>

namespace <?= $namespace ?>;

<?= $use_statements; ?>

/**
 * @extends ServiceEntityRepository<<?= $credential_entity_class_name ?>>
 */
class <?= $class_name ?> extends ServiceEntityRepository implements CredentialRecordRepositoryInterface, CanSaveCredentialRecord
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, <?= $credential_entity_class_name ?>::class);
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $userEntity): array
    {
        $credentials = $this->findBy(['userHandle' => $userEntity->id]);

        return array_map(
            static fn (<?= $credential_entity_class_name ?> $credential) => $credential->toPublicKeyCredentialSource(),
            $credentials
        );
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $credential = $this->findOneBy(['publicKeyCredentialId' => $publicKeyCredentialId]);

        return $credential?->toPublicKeyCredentialSource();
    }

    public function saveCredentialRecord(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $credential = $this->findOneBy(['publicKeyCredentialId' => $publicKeyCredentialSource->publicKeyCredentialId]);

        if (null === $credential) {
            $credential = <?= $credential_entity_class_name ?>::fromPublicKeyCredentialSource($publicKeyCredentialSource);
        } else {
            $credential->counter = $publicKeyCredentialSource->counter;
        }

        $this->getEntityManager()->persist($credential);
        $this->getEntityManager()->flush();
    }
}
