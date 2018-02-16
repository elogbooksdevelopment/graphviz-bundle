<?php

namespace Sixpaths\GraphvizBundle\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Transition
{
    /**
     * @Required
     *
     * @var string
     */
    public $name;

    /**
     * @Required
     *
     * @var string[]
     */
    public $from;

    /**
     * @Required
     *
     * @var string
     */
    public $to;

    /**
     * @Optional
     *
     * @var string
     */
    public $colour = '#000000';

    /**
     * @Optional
     *
     * @var string
     */
    public $submachine;

    /**
     * @Optional
     *
     * @var array<string, string>
     */
    public $attributes;

    /**
     * @Optional
     *
     * @var string
     */
    public $question;

    /**
     * @Optional
     *
     * @var string
     */
    public $answer;
}
