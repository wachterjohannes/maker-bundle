<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\TrustPath;

#[ORM\Entity(repositoryClass: <?= $repository_full_class_name ?>::class)]
#[ORM\Table(name: 'webauthn_credentials')]
class <?= $class_name."\n" ?>
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    public Ulid $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    public string $publicKeyCredentialId;

    #[ORM\Column(type: 'string', length: 255)]
    public string $type;

    #[ORM\Column(type: 'json')]
    public array $transports = [];

    #[ORM\Column(type: 'string', length: 255)]
    public string $attestationType;

    #[ORM\Column(type: 'trust_path', nullable: true)]
    public ?TrustPath $trustPath = null;

    #[ORM\Column(type: 'aaguid', length: 36)]
    public string $aaguid;

    #[ORM\Column(type: 'base64', length: 1024)]
    public string $credentialPublicKey;

    #[ORM\Column(type: 'string', length: 255)]
    public string $userHandle;

    #[ORM\Column(type: 'integer')]
    public int $counter = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $otherUI = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    public ?bool $backupEligible = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    public ?bool $backupStatus = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    public ?bool $uvInitialized = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $name = null;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromPublicKeyCredentialSource(PublicKeyCredentialSource $source): self
    {
        $credential = new self();
        $credential->publicKeyCredentialId = $source->publicKeyCredentialId;
        $credential->type = $source->type;
        $credential->transports = $source->transports;
        $credential->attestationType = $source->attestationType;
        $credential->trustPath = $source->trustPath;
        $credential->aaguid = $source->aaguid;
        $credential->credentialPublicKey = $source->credentialPublicKey;
        $credential->userHandle = $source->userHandle;
        $credential->counter = $source->counter;
        $credential->otherUI = $source->otherUI;
        $credential->backupEligible = $source->backupEligible;
        $credential->backupStatus = $source->backupStatus;
        $credential->uvInitialized = $source->uvInitialized;

        return $credential;
    }

    public function toPublicKeyCredentialSource(): PublicKeyCredentialSource
    {
        return PublicKeyCredentialSource::create(
            $this->publicKeyCredentialId,
            $this->type,
            $this->transports,
            $this->attestationType,
            $this->trustPath,
            $this->aaguid,
            $this->credentialPublicKey,
            $this->userHandle,
            $this->counter,
        )
            ->setOtherUI($this->otherUI)
            ->setBackupEligible($this->backupEligible)
            ->setBackupStatus($this->backupStatus)
            ->setUvInitialized($this->uvInitialized)
        ;
    }
}
