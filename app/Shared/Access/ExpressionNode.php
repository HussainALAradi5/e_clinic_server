<?php

declare(strict_types=1);

namespace App\Shared\Access;

/**
 * One node of a parsed permission expression (AST).
 */
final readonly class ExpressionNode
{
    public const PERMISSION = 'perm';

    public const NOT = 'not';

    public const AND = 'and';

    public const OR = 'or';

    /**
     * @param  self::PERMISSION|self::NOT|self::AND|self::OR  $kind
     * @param  list<ExpressionNode>  $children
     */
    private function __construct(
        public string $kind,
        public ?string $permission = null,
        public array $children = [],
    ) {}

    public static function permission(string $name): self
    {
        return new self(self::PERMISSION, $name);
    }

    public static function not(self $child): self
    {
        return new self(self::NOT, children: [$child]);
    }

    /**
     * @param  self::AND|self::OR  $kind
     * @param  list<ExpressionNode>  $children
     */
    public static function group(string $kind, array $children): self
    {
        return count($children) === 1 ? $children[0] : new self($kind, children: $children);
    }
}
