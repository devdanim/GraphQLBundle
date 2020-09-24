<?php

namespace Youshido\GraphQLBundle\Event;

use Symfony\Component\EventDispatcher\GenericEvent;
use Youshido\GraphQL\Field\FieldInterface;
use Youshido\GraphQL\Parser\Ast\Field;

class ResolveEvent extends GenericEvent
{
    /**
     * @var Field */
    protected $field;

    /** @var array */
    protected $astFields;

    /** @var mixed|null */
    protected $resolvedValue;

    /** @var mixed|null */
    protected $parentValue;

    /**
     * Constructor.
     *
     * @param FieldInterface $field
     * @param mixed $astFields
     * @param mixed|null $resolvedValue
     * @param mixed|null $parentValue
     */
    public function __construct(FieldInterface $field, $astFields, $resolvedValue = null, $parentValue = null)
    {
        $this->field = $field;
        $this->astFields = $astFields;
        $this->resolvedValue = $resolvedValue;
        $this->parentValue = $parentValue;
        parent::__construct('ResolveEvent', [$field, $astFields, $resolvedValue, $parentValue]);
    }

    /**
     * Returns the field.
     *
     * @return FieldInterface
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Returns the AST fields.
     *
     * @return array
     */
    public function getAstFields()
    {
        return $this->astFields;
    }

    /**
     * Returns the resolved value.
     *
     * @return mixed|null
     */
    public function getResolvedValue()
    {
        return $this->resolvedValue;
    }

    /**
     * Returns the parent value.
     *
     * @return mixed|null
     */
    public function getParentValue()
    {
        return $this->parentValue;
    }

    /**
     * Allows the event listener to manipulate the resolved value.
     *
     * @param $resolvedValue
     */
    public function setResolvedValue($resolvedValue)
    {
        $this->resolvedValue = $resolvedValue;
    }
}

