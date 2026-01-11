<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Maker\Security;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Maker\Common\InstallDependencyTrait;
use Symfony\Bundle\MakerBundle\Security\InteractiveSecurityHelper;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;
use Symfony\Bundle\MakerBundle\Util\YamlSourceManipulator;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Generate WebAuthn authentication setup.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
final class MakeWebauthn extends AbstractMaker
{
    use InstallDependencyTrait;

    private const SECURITY_CONFIG_PATH = 'config/packages/security.yaml';
    private const WEBAUTHN_CONFIG_PATH = 'config/packages/webauthn.yaml';

    private string $userClass;
    private string $userNameField;
    private string $relyingPartyId;
    private string $relyingPartyName;
    private string $credentialEntityClass;
    private string $credentialRepositoryClass;
    private string $userEntityRepositoryClass;
    private string $authenticatorClass;

    public function __construct(
        private FileManager $fileManager,
        private Generator $generator,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:security:webauthn';
    }

    public static function getCommandDescription(): string
    {
        return 'Generate the code needed for WebAuthn authentication';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpFileContents('security/MakeWebauthn.txt'))
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(
            SecurityBundle::class,
            'security'
        );

        $dependencies->addClassDependency(TwigBundle::class, 'twig');

        $dependencies->addClassDependency(
            Yaml::class,
            'yaml'
        );

        $dependencies->addClassDependency(DoctrineBundle::class, 'orm');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        // Check if webauthn bundle is installed
        $this->installDependencyIfNeeded(
            io: $io,
            expectedClassToExist: 'Webauthn\\Bundle\\WebauthnBundle',
            composerPackage: 'web-auth/webauthn-symfony-bundle'
        );

        if (!$this->fileManager->fileExists(self::SECURITY_CONFIG_PATH)) {
            throw new RuntimeCommandException(\sprintf('The file "%s" does not exist. PHP & XML configuration formats are currently not supported.', self::SECURITY_CONFIG_PATH));
        }

        $ysm = new YamlSourceManipulator($this->fileManager->getFileContents(self::SECURITY_CONFIG_PATH));
        $securityData = $ysm->getData();

        if (!isset($securityData['security']['providers']) || !$securityData['security']['providers']) {
            throw new RuntimeCommandException('To generate WebAuthn authentication, you must configure at least one entry under "providers" in "security.yaml".');
        }

        $io->title('WebAuthn Authentication Setup');
        $io->text([
            'This command will help you set up WebAuthn authentication in your Symfony application.',
            'It will generate:',
            '  * A WebAuthn credential entity and repository',
            '  * A WebAuthn user entity repository',
            '  * A custom WebAuthn authenticator',
            '  * Configuration files for WebAuthn',
            '  * A login controller and template',
        ]);
        $io->newLine();

        // Get user class information
        $securityHelper = new InteractiveSecurityHelper();
        $this->userClass = $securityHelper->guessUserClass($io, $securityData['security']['providers']);
        $this->userNameField = $securityHelper->guessUserNameField($io, $this->userClass, $securityData['security']['providers']);

        if (false !== stripos($this->userNameField, 'email')) {
            $io->note([
                'Note: For WebAuthn, it is recommended to use a username field rather than email.',
                'This helps protect user privacy as the username may be stored on authenticators.',
                'Consider using a separate username property for better privacy protection.',
            ]);
        }

        // Relying Party configuration
        $io->section('Relying Party Configuration');
        $io->text([
            'The Relying Party ID must be a valid domain name (e.g., <fg=yellow>example.com</> or <fg=yellow>localhost</>).',
            'Do NOT include: scheme (https://), port (:8000), path (/page), or IP addresses.',
        ]);
        $this->relyingPartyId = $io->ask(
            'What is your Relying Party ID',
            'localhost',
            static function (mixed $answer) {
                return Validator::notBlank($answer);
            }
        );

        $this->relyingPartyName = $io->ask(
            'What is your Relying Party Name (application name)',
            'My WebAuthn App',
            static function (mixed $answer) {
                return Validator::notBlank($answer);
            }
        );

        // Class names
        $io->section('Class Names');
        $this->credentialEntityClass = $io->ask(
            'Name of the credential entity class (e.g. <fg=yellow>WebauthnCredential</>)',
            'WebauthnCredential',
            Validator::validateClassName(...)
        );

        $this->credentialRepositoryClass = $io->ask(
            'Name of the credential repository class (e.g. <fg=yellow>WebauthnCredentialRepository</>)',
            'WebauthnCredentialRepository',
            Validator::validateClassName(...)
        );

        $this->userEntityRepositoryClass = $io->ask(
            'Name of the user entity repository class (e.g. <fg=yellow>WebauthnUserEntityRepository</>)',
            'WebauthnUserEntityRepository',
            Validator::validateClassName(...)
        );

        $this->authenticatorClass = $io->ask(
            'Name of the authenticator class (e.g. <fg=yellow>WebauthnAuthenticator</>)',
            'WebauthnAuthenticator',
            Validator::validateClassName(...)
        );
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        // 1. Generate the Credential Entity
        $credentialEntityClassDetails = $generator->createClassNameDetails(
            $this->credentialEntityClass,
            'Entity\\',
            ''
        );

        $generator->generateClass(
            $credentialEntityClassDetails->getFullName(),
            'security/webauthn/WebauthnCredential.tpl.php',
            [
                'repository_full_class_name' => $generator->createClassNameDetails(
                    $this->credentialRepositoryClass,
                    'Repository\\',
                    ''
                )->getFullName(),
            ]
        );

        // 2. Generate the Credential Repository
        $credentialRepositoryClassDetails = $generator->createClassNameDetails(
            $this->credentialRepositoryClass,
            'Repository\\',
            ''
        );

        $useStatements = new UseStatementGenerator([
            'Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository',
            'Doctrine\Persistence\ManagerRegistry',
            'Webauthn\Bundle\Repository\CanSaveCredentialRecord',
            'Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface',
            'Webauthn\PublicKeyCredentialSource',
            'Webauthn\PublicKeyCredentialUserEntity',
            $credentialEntityClassDetails->getFullName(),
        ]);

        $generator->generateClass(
            $credentialRepositoryClassDetails->getFullName(),
            'security/webauthn/WebauthnCredentialRepository.tpl.php',
            [
                'use_statements' => $useStatements,
                'credential_entity_class_name' => $credentialEntityClassDetails->getShortName(),
                'credential_entity_full_class_name' => $credentialEntityClassDetails->getFullName(),
            ]
        );

        // 3. Generate the User Entity Repository
        $userEntityRepositoryClassDetails = $generator->createClassNameDetails(
            $this->userEntityRepositoryClass,
            'Repository\\',
            ''
        );

        $userClassDetails = $generator->createClassNameDetails(
            '\\'.$this->userClass,
            'Entity\\'
        );

        // Determine the User repository class name
        $userRepositoryClassName = $userClassDetails->getShortName().'Repository';
        $userRepositoryClassDetails = $generator->createClassNameDetails(
            $userRepositoryClassName,
            'Repository\\',
            ''
        );

        $useStatements = new UseStatementGenerator([
            'Symfony\Component\Uid\Ulid',
            'Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface',
            'Webauthn\PublicKeyCredentialUserEntity',
            $userClassDetails->getFullName(),
            $userRepositoryClassDetails->getFullName(),
        ]);

        $generator->generateClass(
            $userEntityRepositoryClassDetails->getFullName(),
            'security/webauthn/WebauthnUserEntityRepository.tpl.php',
            [
                'use_statements' => $useStatements,
                'user_entity_class_name' => $userClassDetails->getShortName(),
                'user_repository_class_name' => $userRepositoryClassDetails->getShortName(),
                'user_name_field' => $this->userNameField,
            ]
        );

        // 4. Generate the Custom Authenticator
        $authenticatorClassDetails = $generator->createClassNameDetails(
            $this->authenticatorClass,
            'Security\\',
            'Authenticator'
        );

        $useStatements = new UseStatementGenerator([
            'Symfony\Component\HttpFoundation\RedirectResponse',
            'Symfony\Component\HttpFoundation\Request',
            'Symfony\Component\HttpFoundation\Response',
            'Symfony\Component\Routing\Generator\UrlGeneratorInterface',
            'Symfony\Component\Security\Core\Authentication\Token\TokenInterface',
            'Symfony\Component\Security\Http\Authenticator\Passport\Passport',
            'Webauthn\Bundle\Security\Authentication\WebauthnAuthenticator as BaseWebauthnAuthenticator',
            'Webauthn\Bundle\Security\Authentication\WebauthnBadge',
            'Webauthn\Bundle\Security\Authentication\WebauthnPassport',
        ]);

        $generator->generateClass(
            $authenticatorClassDetails->getFullName(),
            'security/webauthn/WebauthnAuthenticator.tpl.php',
            [
                'use_statements' => $useStatements,
            ]
        );

        // 5. Generate WebAuthn configuration file
        $webauthnConfigContent = $this->generateWebauthnConfig(
            $credentialRepositoryClassDetails,
            $userEntityRepositoryClassDetails
        );

        $generator->dumpFile(self::WEBAUTHN_CONFIG_PATH, $webauthnConfigContent);

        // 6. Update security.yaml
        $this->updateSecurityConfig($generator, $authenticatorClassDetails);

        // 7. Generate Login Controller
        $this->generateLoginController($generator);

        // 8. Generate Login Template
        $this->generateLoginTemplate($generator);

        // 9. Update .env file
        $this->updateEnvFile($generator);

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            'Next steps:',
            \sprintf('  1. Review the configuration in <info>%s</info>', self::WEBAUTHN_CONFIG_PATH),
            '  2. Create and run a database migration: <info>php bin/console make:migration && php bin/console doctrine:migrations:migrate</info>',
            '  3. Install the WebAuthn Stimulus controller: <info>composer require web-auth/webauthn-stimulus</info>',
            '  4. Enable the Stimulus controller in <info>assets/controllers.json</info>:',
            '     {',
            '       "controllers": {',
            '         "@web-auth/webauthn-stimulus": {',
            '           "enabled": true,',
            '           "fetch": "eager"',
            '         }',
            '       }',
            '     }',
            \sprintf('  5. Review and customize the login template at <info>templates/security/login.html.twig</info>'),
            '  6. Update your <info>.env</info> file with the correct Relying Party configuration',
            '  7. Make sure to use HTTPS (required for WebAuthn): <info>symfony server:ca:install && symfony serve</info>',
            '',
            'For more information, see: https://webauthn-doc.spomky-labs.com/',
        ]);
    }

    private function generateWebauthnConfig(
        \Symfony\Bundle\MakerBundle\Util\ClassNameDetails $credentialRepositoryClassDetails,
        \Symfony\Bundle\MakerBundle\Util\ClassNameDetails $userEntityRepositoryClassDetails
    ): string {
        $config = <<<YAML
webauthn:
    credential_repository: {$credentialRepositoryClassDetails->getFullName()}
    user_repository: {$userEntityRepositoryClassDetails->getFullName()}

    creation_profiles:
        default:
            rp:
                id: '%env(WEBAUTHN_RP_ID)%'
                name: '%env(WEBAUTHN_RP_NAME)%'
            challenge_length: 32
            timeout: 60000
            authenticator_selection_criteria:
                userVerification: 'preferred'
                residentKey: 'preferred'
            attestation_conveyance: 'none'
            public_key_credential_parameters:
                - { type: 'public-key', alg: -7 }   # ES256
                - { type: 'public-key', alg: -257 } # RS256

    request_profiles:
        default:
            rp_id: '%env(WEBAUTHN_RP_ID)%'
            challenge_length: 32
            timeout: 60000
            user_verification: 'preferred'
YAML;

        return $config;
    }

    private function updateSecurityConfig(Generator $generator, \Symfony\Bundle\MakerBundle\Util\ClassNameDetails $authenticatorClassDetails): void
    {
        $ysm = new YamlSourceManipulator($this->fileManager->getFileContents(self::SECURITY_CONFIG_PATH));
        $securityData = $ysm->getData();

        // Add custom authenticator to main firewall
        if (!isset($securityData['security']['firewalls']['main']['custom_authenticators'])) {
            $securityData['security']['firewalls']['main']['custom_authenticators'] = [];
        }

        $securityData['security']['firewalls']['main']['custom_authenticators'][] = $authenticatorClassDetails->getFullName();

        // Add logout if not already present
        if (!isset($securityData['security']['firewalls']['main']['logout'])) {
            $securityData['security']['firewalls']['main']['logout'] = [
                'path' => 'app_logout',
            ];
        }

        $ysm->setData($securityData);
        $generator->dumpFile(self::SECURITY_CONFIG_PATH, $ysm->getContents());
    }

    private function generateLoginController(Generator $generator): void
    {
        $useStatements = new UseStatementGenerator([
            'Symfony\Bundle\FrameworkBundle\Controller\AbstractController',
            'Symfony\Component\HttpFoundation\Response',
            'Symfony\Component\Routing\Attribute\Route',
            'Symfony\Component\Security\Http\Authentication\AuthenticationUtils',
        ]);

        $generator->generateController(
            'App\\Controller\\SecurityController',
            'security/webauthn/SecurityController.tpl.php',
            [
                'use_statements' => $useStatements,
            ]
        );
    }

    private function generateLoginTemplate(Generator $generator): void
    {
        $generator->generateTemplate(
            'security/login.html.twig',
            'security/webauthn/login.tpl.php',
            [
                'username_label' => Str::asHumanWords($this->userNameField),
            ]
        );
    }

    private function updateEnvFile(Generator $generator): void
    {
        $envPath = '.env';

        if (!$this->fileManager->fileExists($envPath)) {
            return;
        }

        $envContent = $this->fileManager->getFileContents($envPath);

        if (str_contains($envContent, 'WEBAUTHN_RP_ID')) {
            return; // Already configured
        }

        $envAddition = <<<ENV


###> webauthn/webauthn-symfony-bundle ###
WEBAUTHN_RP_ID={$this->relyingPartyId}
WEBAUTHN_RP_NAME="{$this->relyingPartyName}"
###< webauthn/webauthn-symfony-bundle ###
ENV;

        $generator->dumpFile($envPath, $envContent.$envAddition);
    }
}
