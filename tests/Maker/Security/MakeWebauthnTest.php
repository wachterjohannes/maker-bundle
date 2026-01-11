<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Tests\Maker\Security;

use Symfony\Bundle\MakerBundle\Maker\Security\MakeWebauthn;
use Symfony\Bundle\MakerBundle\Test\MakerTestCase;
use Symfony\Bundle\MakerBundle\Test\MakerTestRunner;

/**
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class MakeWebauthnTest extends MakerTestCase
{
    protected function getMakerClass(): string
    {
        return MakeWebauthn::class;
    }

    public function getTestDetails(): \Generator
    {
        yield 'generates_webauthn_using_defaults' => [$this->createMakerTest()
            ->run(function (MakerTestRunner $runner) {
                $this->makeUser($runner);

                $output = $runner->runMaker([
                    'localhost', // Relying Party ID
                    'My WebAuthn App', // Relying Party Name
                    'WebauthnCredential', // Credential Entity Class
                    'WebauthnCredentialRepository', // Credential Repository Class
                    'WebauthnUserEntityRepository', // User Entity Repository Class
                    'WebauthnAuthenticator', // Authenticator Class
                ]);

                $this->assertStringContainsString('Success', $output);

                // Check that files were created
                $this->assertFileExists($runner->getPath('src/Entity/WebauthnCredential.php'));
                $this->assertFileExists($runner->getPath('src/Repository/WebauthnCredentialRepository.php'));
                $this->assertFileExists($runner->getPath('src/Repository/WebauthnUserEntityRepository.php'));
                $this->assertFileExists($runner->getPath('src/Security/WebauthnAuthenticator.php'));
                $this->assertFileExists($runner->getPath('src/Controller/SecurityController.php'));
                $this->assertFileExists($runner->getPath('templates/security/login.html.twig'));

                // Check configuration files
                $this->assertFileExists($runner->getPath('config/packages/webauthn.yaml'));

                $webauthnConfig = $runner->readYaml('config/packages/webauthn.yaml');
                $this->assertArrayHasKey('webauthn', $webauthnConfig);
                $this->assertSame('App\\Repository\\WebauthnCredentialRepository', $webauthnConfig['webauthn']['credential_repository']);
                $this->assertSame('App\\Repository\\WebauthnUserEntityRepository', $webauthnConfig['webauthn']['user_repository']);

                // Check security.yaml was updated
                $securityConfig = $runner->readYaml('config/packages/security.yaml');
                $this->assertContains(
                    'App\\Security\\WebauthnAuthenticator',
                    $securityConfig['security']['firewalls']['main']['custom_authenticators']
                );
                $this->assertArrayHasKey('logout', $securityConfig['security']['firewalls']['main']);

                // Check .env was updated
                $envContent = file_get_contents($runner->getPath('.env'));
                $this->assertStringContainsString('WEBAUTHN_RP_ID=localhost', $envContent);
                $this->assertStringContainsString('WEBAUTHN_RP_NAME="My WebAuthn App"', $envContent);
            }),
        ];

        yield 'generates_webauthn_with_custom_names' => [$this->createMakerTest()
            ->run(function (MakerTestRunner $runner) {
                $this->makeUser($runner);

                $output = $runner->runMaker([
                    'example.com', // Relying Party ID
                    'Custom App', // Relying Party Name
                    'PasskeyCredential', // Credential Entity Class
                    'PasskeyCredentialRepository', // Credential Repository Class
                    'PasskeyUserRepository', // User Entity Repository Class
                    'PasskeyAuthenticator', // Authenticator Class
                ]);

                $this->assertStringContainsString('Success', $output);

                // Check that files were created with custom names
                $this->assertFileExists($runner->getPath('src/Entity/PasskeyCredential.php'));
                $this->assertFileExists($runner->getPath('src/Repository/PasskeyCredentialRepository.php'));
                $this->assertFileExists($runner->getPath('src/Repository/PasskeyUserRepository.php'));
                $this->assertFileExists($runner->getPath('src/Security/PasskeyAuthenticator.php'));

                // Check configuration uses custom class names
                $webauthnConfig = $runner->readYaml('config/packages/webauthn.yaml');
                $this->assertSame('App\\Repository\\PasskeyCredentialRepository', $webauthnConfig['webauthn']['credential_repository']);
                $this->assertSame('App\\Repository\\PasskeyUserRepository', $webauthnConfig['webauthn']['user_repository']);

                // Check .env was updated with custom values
                $envContent = file_get_contents($runner->getPath('.env'));
                $this->assertStringContainsString('WEBAUTHN_RP_ID=example.com', $envContent);
                $this->assertStringContainsString('WEBAUTHN_RP_NAME="Custom App"', $envContent);
            }),
        ];
    }

    private function makeUser(MakerTestRunner $runner, string $identifier = 'email'): void
    {
        $runner->runConsole('make:user', [
            'User', // Class Name
            'y', // Create as Entity
            $identifier, // Property used to identify the user
            'y', // Uses a password
        ]);
    }
}
