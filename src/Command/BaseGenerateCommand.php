<?php

namespace Yiisoft\Yii\Gii\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Yiisoft\Yii\Console\ExitCode;
use Yiisoft\Yii\Gii\GeneratorInterface;
use Yiisoft\Yii\Gii\GiiInterface;

abstract class BaseGenerateCommand extends Command
{
    protected const NAME = '';
    protected GiiInterface $gii;

    public function __construct(GiiInterface $gii)
    {
        parent::__construct();
        $this->gii      = $gii;
    }

    protected function configure(): void
    {
        $this->addArgument('overwrite', InputArgument::OPTIONAL, '', false);
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $generator = $this->gii->getGenerator(static::NAME);
        echo "Running '{$generator->getName()}'...\n\n";
        if ($generator->validate()) {
            $this->generateCode($generator, $input, $output);
        } else {
            $this->displayValidationErrors($generator, $output);
        }
        return ExitCode::OK;
    }

    protected function displayValidationErrors(GeneratorInterface $generator, OutputInterface $output)
    {
        $output->writeln("Code not generated. Please fix the following errors:\n\n");
        foreach ($generator->errors as $attribute => $errors) {
            echo ' - '.$output->writeln($attribute).': '.implode(
                    '; ',
                    $errors
                )."\n";
        }
        echo "\n";
    }

    protected function generateCode(GeneratorInterface $generator, InputInterface $input, OutputInterface $output)
    {
        $files = $generator->generate();
        $n     = count($files);
        if ($n === 0) {
            echo "No code to be generated.\n";
            return;
        }
        echo "The following files will be generated:\n";
        $skipAll = $input->getArgument('interactive') ? null : !$input->getArgument('overwrite');
        $answers = [];
        foreach ($files as $file) {
            $path = $file->getRelativePath();
            if (is_file($file->getPath())) {
                if (file_get_contents($file->getPath()) === $file->getContent()) {
                    $output->writeln('  [unchanged]');
                    $output->writeln(" $path\n");
                    $answers[$file->getId()] = false;
                } else {
                    echo '    '.$output->writeln('[changed]');
                    echo $output->writeln(" $path\n");
                    if ($skipAll !== null) {
                        $answers[$file->getId()] = !$skipAll;
                    } else {
                        $answer                  = $this->getHelper(
                            "Do you want to overwrite this file?",
                            [
                                'y'  => 'Overwrite this file.',
                                'n'  => 'Skip this file.',
                                'ya' => 'Overwrite this and the rest of the changed files.',
                                'na' => 'Skip this and the rest of the changed files.',
                            ]
                        );
                        $answers[$file->getId()] = $answer === 'y' || $answer === 'ya';
                        if ($answer === 'ya') {
                            $skipAll = false;
                        } elseif ($answer === 'na') {
                            $skipAll = true;
                        }
                    }
                }
            } else {
                echo '        '.$output->writeln('[new]');
                echo $output->writeln(" $path\n");
                $answers[$file->getId()] = true;
            }
        }

        if (!array_sum($answers)) {
            $output->writeln("\nNo files were chosen to be generated.\n");
            return;
        }

        if (!$output->writeln("\nReady to generate the selected files?", true)) {
            $output->writeln("\nNo file was generated.\n");
            return;
        }

        if ($generator->save($files, (array)$answers, $results)) {
            $output->writeln("\nFiles were generated successfully!\n");
        } else {
            $output->writeln("\nSome errors occurred while generating the files.");
        }
        echo preg_replace('%<span class="error">(.*?)</span>%', '\1', $results)."\n";
    }

    protected function confirm($input, $output)
    {
        $question = new ConfirmationQuestion("\nReady to generate the selected files?", true);
        return $this->getHelper('question')->ask($input, $output, $question);
    }

    protected function choice($input, $output)
    {
        $question = new ChoiceQuestion('Do you want to overwrite this file?', [
            'y' => 'Overwrite this file.',
            'n' => 'Skip this file.',
            'ya' => 'Overwrite this and the rest of the changed files.',
            'na' => 'Skip this and the rest of the changed files.',
        ]);
        return $this->getHelper('question')->ask($input, $output, $question);
    }
}
