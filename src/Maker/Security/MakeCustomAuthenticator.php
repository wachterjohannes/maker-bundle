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

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Maker\Common\InstallDependencyTrait;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;
use Symfony\Bundle\MakerBundle\Util\YamlSourceManipulator;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * @author Jesse Rushlow <jr@rushlow.dev>
 *
 * @internal
 */
final class MakeCustomAuthenticator extends AbstractMaker
{
    use InstallDependencyTrait;

    private const SECURITY_CONFIG_PATH = 'config/packages/security.yaml';

    public function __construct(
        private FileManager $fileManager,
        private Generator $generator,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:security:custom';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a custom security authenticator.';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, 'The class name of the authenticator to create (e.g. <fg=yellow>CustomAuthenticator</>)')
            ->setHelp($this->getHelpFileContents('security/MakeCustom.txt'))
        ;

        $inputConfig->setArgumentAsNonInteractive('name');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $this->installDependencyIfNeeded(
            io: $io,
            expectedClassToExist: AbstractAuthenticator::class,
            composerPackage: 'symfony/security-bundle'
        );

        $this->checkSecurityConfigExists();

        if ($input->getArgument('name')) {
            return;
        }

        $input->setArgument('name', $io->ask(
            question: 'What is the class name of the authenticator (e.g. <fg=yellow>CustomAuthenticator</>)',
            validator: static function (mixed $answer) {
                return Validator::notBlank($answer);
            }
        ));
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        // interact() installs the bundle whose recipe writes security.yaml, and it does not
        // run without a terminal. Installing twice would be a second composer call, so this
        // only steps in when interact() did not already do it.
        if (!$this->fileManager->fileExists(self::SECURITY_CONFIG_PATH)) {
            $this->installDependencyIfNeeded(
                io: $io,
                expectedClassToExist: AbstractAuthenticator::class,
                composerPackage: 'symfony/security-bundle'
            );
        }

        $this->checkSecurityConfigExists();

        if (!$name = $input->getArgument('name')) {
            throw new RuntimeCommandException('The "name" argument is required when the command runs without asking.');
        }

        $authenticatorClassName = $this->generator->createClassNameDetails(
            name: $name,
            namespacePrefix: 'Security\\',
            suffix: 'Authenticator'
        );

        // Configure security to use custom authenticator
        $securityConfig = ($ysm = new YamlSourceManipulator(
            $this->fileManager->getFileContents(self::SECURITY_CONFIG_PATH)
        ))->getData();

        $securityConfig['security']['firewalls']['main']['custom_authenticators'] = [$authenticatorClassName->getFullName()];

        $ysm->setData($securityConfig);
        $generator->dumpFile(self::SECURITY_CONFIG_PATH, $ysm->getContents());

        // Generate the new authenticator
        $useStatements = new UseStatementGenerator([
            Request::class,
            Response::class,
            TokenInterface::class,
            AuthenticationException::class,
            AbstractAuthenticator::class,
            Passport::class,
            JsonResponse::class,
            UserBadge::class,
            CustomUserMessageAuthenticationException::class,
            SelfValidatingPassport::class,
        ]);

        $generator->generateClass(
            className: $authenticatorClassName->getFullName(),
            templateName: 'security/custom/Authenticator.tpl.php',
            variables: [
                'use_statements' => $useStatements,
                'class_short_name' => $authenticatorClassName->getShortName(),
            ]
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    private function checkSecurityConfigExists(): void
    {
        if (!$this->fileManager->fileExists(self::SECURITY_CONFIG_PATH)) {
            throw new RuntimeCommandException(\sprintf('The file "%s" does not exist. PHP & XML configuration formats are currently not supported.', self::SECURITY_CONFIG_PATH));
        }
    }
}
