<?php
/**
 * Bookmeka_TransformTask class
 *
 * A task to regenerate the derivated texts from an XML/TEI
 *
 * @copyright Copyright 2015 frederic.glorieux@fictif.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package Bookmeka
 */
class Bookmeka_TransformTask extends Omeka_Job_AbstractJob
{
    const QUEUE_NAME = 'bookmeka_transform';

    public function __construct(array $options)
    {
    }
}
