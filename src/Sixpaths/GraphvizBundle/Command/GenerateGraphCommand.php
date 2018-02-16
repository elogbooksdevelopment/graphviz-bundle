<?php

namespace Sixpaths\GraphvizBundle\Command;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Reader;
use Sixpaths\GraphvizBundle\Annotation\Transition;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GenerateGraphCommand extends ContainerAwareCommand
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var Reader
     */
    private $reader;

    protected $target;
    protected $transitions = [];
    protected $states = [];
    protected $submachines = [];
    protected $usedTransitions = [];

    protected function configure()
    {
        $this->setName('sixpaths:graphviz:generate')
            ->setDescription('Generate a graphviz graph for a given target class')
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'The target class for which to generate a graph'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    )
    {
        $this->input = $input;
        $this->output = $output;
        $this->container = $this->getContainer();

        $this->reader = $this->container->get('annotation_reader');
        $this->target = $this->getReflectionClass();

        $this->init();
    }

    private function init(): void
    {
        foreach ($this->target->getMethods() as $method) {
            $this->parseMethod($method);
        }

        $this->parseTransitions();

        $this->generateOutput();
    }

    private function parseMethod(\ReflectionMethod $method): void
    {
        $annotations = $this->reader->getMethodAnnotations($method);

        foreach ($annotations as $annotation) {
            if (!$annotation instanceof Transition) {
                continue;
            }

            $this->parseAnnotation($annotation);
        }
    }

    private function parseAnnotation(Transition $transition): void
    {
        $this->transitions[] = $transition;
        $this->parseSubMachine($transition);
    }

    private function parseTransitions(): void
    {
        foreach ($this->transitions as $transition) {
        }
    }

    private function parseSubMachine(Transition $transition): void
    {
        if (is_null($transition->submachine)) {
            return;
        }

        if (!array_key_exists($transition->submachine, $this->submachines)) {
            $this->submachines[$transition->submachine] = [];
        }

        $this->submachines[$transition->submachine][] = $transition;
    }

    private function generateOutput(): void
    {
        $output = [];

        $output[] = "digraph " . $this->target->getShortName() . "Graph {";
        $output[] = "}";

        print(
            $this->getGraphHeader() .
            $this->getGraphSubMachines() .
            $this->getGraphProxyStates() .
            $this->getGraphStates() .
            $this->getGraphFooter()
        );
    }

    private function getGraphHeader(): string
    {
        return sprintf("
            digraph %sGraph {
                node [shape = rectangle nodesep = 2.0]
                graph [overlap = false]
                rankdir = LR
                splines = spline
                size = \"20,10\"
        ", $this->target->getShortName());
    }

    private function getGraphSubMachines(): string
    {
        $output = [];

        foreach ($this->submachines as $name => $states) {
            $outputStates = [];
            foreach ($states as $transition) {
                $transition = clone $transition;
                $this->removeAttribute($transition, 'label');
                $outputStates[] = sprintf("\"%s\" %s", $transition->to, $this->getTransitionAttributes($transition));
            }

            $output[] = sprintf("
                subgraph cluster%s {
                    style = filled
                    color = \"#efefef\"
                    node [style = filled fillcolor = white color = grey]

                    %s
                }
            ", $name, implode(" ", $outputStates));
        }

        return implode("", $output);
    }

    private function getGraphProxyStates(): string
    {
        $output = [];

        foreach ($this->transitions as $transition) {
            if (!isset($transition->question) || !isset($transition->answer)) {
                continue;
            }

            $output[] = sprintf("
                \"%s\" [shape = diamond]
            ", $transition->question);
        }

        return implode("", $output);
    }

    private function getGraphStates(): string
    {
        $output = [];

        foreach ($this->transitions as $transition) {
            foreach ($transition->from as $fromState) {
                if (isset($transition->question) && $transition->answer) {
                    $transition1 = $transition;
                    $transition2 = clone $transition;

                    if (!in_array($this->getTransitionName($transition1, $fromState), $this->usedTransitions)) {
                        $output[] = sprintf("\"%s\" -> \"%s\" %s", $fromState, $transition1->question, $this->getTransitionAttributes($transition1));
                        $this->usedTransitions[] = $fromState . '->' . $transition1->question;
                    }

                    $this->addAttributes($transition2, ['label' => $transition2->answer]);
                    $output[] = sprintf("\"%s\" -> \"%s\" %s", $transition2->question, $transition2->to, $this->getTransitionAttributes($transition2));

                    continue;
                }

                $output[] = sprintf("\"%s\" -> \"%s\" %s", $fromState, $transition->to, $this->getTransitionAttributes($transition));
            }
        }

        return implode("\n", $output);
    }

    private function getTransitionName(Transition $transition, string $fromState): string
    {
        if (isset($transition->question)) {
            return $fromState . '->' . $transition->question;
        }

        return $fromState . '->' . $transition->to;
    }

    private function addAttributes(Transition $transition, array $attributes): void
    {
        if (!isset($transition->attributes)) {
            $transition->attributes = [];
        }

        $transition->attributes = array_merge($transition->attributes, $attributes);
    }

    private function removeAttribute(Transition $transition, string $attribute): void
    {
        unset($transition->attributes[$attribute]);
    }

    private function getTransitionAttributes(Transition $transition): string
    {
        if (!isset($transition->attributes)) {
            return '';
        }

        $attributes = [];
        foreach ($transition->attributes as $key => $value) {
            $attributes[] = $key . " = \"" . $value . "\"";
        }

        return "[" . implode(" ", $attributes) . "]";
    }

    private function getGraphFooter(): string
    {
        return "}";
    }

    /**
     * Gets an instance of the target class by reflection
     *
     * @return \ReflectionClass
     */
    private function getReflectionClass(): \ReflectionClass
    {
        $target = $this->input->getArgument('target');

        if (!class_exists($target)) {
            throw new \InvalidArgumentException('The class "' . $target . '" does not exist.');
        }

        return new \ReflectionClass($target);
    }
}
