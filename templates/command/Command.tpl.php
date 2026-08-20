<?= "<?php\n"; ?>

namespace <?= $namespace; ?>;

<?= $use_statements; ?>

#[AsCommand(
    name: '<?= $command_name; ?>',
    description: 'Add a short description for your command',
)]
class <?= $class_name; ?>
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Argument description')] string $arg = '',
        #[Option('Option description')] bool $enable = false,
    ): int {
        $io->note(sprintf('The value of $arg is: %s', $arg));
        $io->note(sprintf('The value of $enable is: %s', $enable ? 'true' : 'false'));

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
