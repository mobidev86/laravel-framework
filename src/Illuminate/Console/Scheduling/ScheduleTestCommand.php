<?php

namespace Illuminate\Console\Scheduling;

use Illuminate\Console\Application;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'schedule:test')]
class ScheduleTestCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'schedule:test {--name= : The name of the scheduled command to run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a scheduled command';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function handle(Schedule $schedule)
    {
        $phpBinary = Application::phpBinary();

        $commands = $schedule->events();

        $commandNames = [];

        foreach ($commands as $command) {
            $commandNames[] = $command->command ?? $command->getSummaryForDisplay();
        }

        if (empty($commandNames)) {
            return $this->components->info('No scheduled commands have been defined.');
        }

        if (! empty($name = $this->option('name'))) {
            $commandBinary = $phpBinary.' '.Application::artisanBinary();

            $matches = array_filter($commandNames, function ($commandName) use ($commandBinary, $name) {
                return trim(str_replace($commandBinary, '', $commandName)) === $name;
            });

            if (count($matches) === 0) {
                $this->components->info('No matching scheduled command found.');
                return;
            }

            $index = key($matches);

            // Handle multiple matches
            if (count($matches) >= 1) {
                $options = array_map(function ($index, $value) {
                    return "$value [$index]";
                }, array_keys($matches), $matches);
                $userInput = $this->components->choice('Multiple matching scheduled commands found. Select one:', $options);
                preg_match('/\[(\d+)\]/', $userInput, $choice);
                $index = (int)$choice[1];
            }
        } else {
            // if there are multiple scheduled commands with the same description
            if(count($commandNames) !== count(array_unique($commandNames))){
                $options = array_map(function ($index, $value) {
                    return "$value [$index]";
                }, array_keys($commandNames), $commandNames);
                $userInput = $this->components->choice('Which command would you like to run?', $options);
                preg_match('/\[(\d+)\]/', $userInput, $choice);
                $index = (int)$choice[1];
            }else{
                $index = array_search($this->components->choice('Which command would you like to run?', $commandNames), $commandNames);
            }
        }

        $event = $commands[$index];

        $summary = $event->getSummaryForDisplay();

        $command = $event instanceof CallbackEvent
            ? $summary
            : trim(str_replace($phpBinary, '', $event->command));

        $description = sprintf(
            'Running [%s]%s',
            $command,
            $event->runInBackground ? ' in background' : '',
        );

        $this->components->task($description, fn () => $event->run($this->laravel));

        if (! $event instanceof CallbackEvent) {
            $this->components->bulletList([$event->getSummaryForDisplay()]);
        }

        $this->newLine();
    }
}
