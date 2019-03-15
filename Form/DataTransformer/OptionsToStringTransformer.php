<?php

declare(strict_types=1);

/*
* @copyright   2019 Mautic, Inc. All rights reserved
* @author      Mautic, Inc.
*
* @link        https://mautic.com
*
* @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace MauticPlugin\CustomObjectsBundle\Form\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\SerializerInterface;
use MauticPlugin\CustomObjectsBundle\Entity\CustomFieldOption;
use Symfony\Component\Form\DataTransformerInterface;

class OptionsToStringTransformer implements DataTransformerInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param SerializerInterface $serializer
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Transforms an object (CustomFieldOption) to a string (json).
     *
     * @param ArrayCollection|CustomFieldOption[]|null $options
     *
     * @return string
     */
    public function transform($options = null): string
    {
        if (!$options) {
            // Options can be null because entities are not using constructors
            return '[]';
        }

        return $this->serializer->serialize(
            $options->map(function (CustomFieldOption $option) {
                return $option->__toArray();
            })->toArray(),
            'json'
        );
    }

    /**
     * Transforms a string (json) to an object (CustomFieldOption).
     *
     * @param string $options
     *
     * @return ArrayCollection|CustomFieldOption[]
     */
    public function reverseTransform($options): ArrayCollection
    {
        $options = json_decode($options, true);

        foreach ($options as $key => $option) {
            // @todo CustomField handling and test when adding & editing custom field
            $options[$key] = new CustomFieldOption($option);
        }

        return new ArrayCollection($options);
    }
}