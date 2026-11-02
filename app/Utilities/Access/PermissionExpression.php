<?php

declare(strict_types=1);

namespace App\Utilities\Access;

use App\Interfaces\Shared\OwnedByUser;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Odoo-style permission expressions: `&` (AND), `|` (OR), `!` (NOT) and parentheses.
 *
 * Example: `(refunds.read_all|refunds.read_own)&refunds.approve`.
 * The Angular AccessService implements the same grammar.
 *
 * Rules: `authenticated` is true for any signed-in user; `x.read_all` implies `x.read_own`;
 * `read_own` / `write` / `delete` on a specific owned record also require ownership unless the user has `x.read_all`;
 * unknown names evaluate to false (fail closed).
 */
final class PermissionExpression
{
    public const AUTHENTICATED = 'authenticated';

    private const OWNED_FLAGS = ['read_own', 'write', 'delete'];

    /** @var array<string, self> */
    private static array $cache = [];

    /** @var list<string> */
    private array $tokens = [];

    private int $position = 0;

    private function __construct(private readonly ExpressionNode $root) {}

    public static function parse(string $expression): self
    {
        return self::$cache[$expression] ??= self::compile($expression);
    }

    public function evaluate(?Authenticatable $user, ?Model $record = null): bool
    {
        return $user !== null && $this->evaluateNode($this->root, $user, $record);
    }

    /**
     * Permission names referenced by the expression.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        $names = [];
        $walk = function (ExpressionNode $node) use (&$walk, &$names): void {
            if ($node->permission !== null) {
                $names[] = $node->permission;
            }
            array_map($walk, $node->children);
        };
        $walk($this->root);

        return array_values(array_unique($names));
    }

    private static function compile(string $expression): self
    {
        preg_match_all('/[a-z0-9_.]+|[&|!()]|\S/', strtolower($expression), $matches);
        $parser = new self(ExpressionNode::permission(''));
        $parser->tokens = $matches[0];

        if ($parser->tokens === []) {
            throw new InvalidArgumentException('Empty permission expression.');
        }

        $root = $parser->parseOr();

        if ($parser->position < count($parser->tokens)) {
            throw new InvalidArgumentException("Unexpected token '{$parser->tokens[$parser->position]}' in permission expression '{$expression}'.");
        }

        return new self($root);
    }

    private function parseOr(): ExpressionNode
    {
        $children = [$this->parseAnd()];
        while ($this->accept('|')) {
            $children[] = $this->parseAnd();
        }

        return ExpressionNode::group(ExpressionNode::OR, $children);
    }

    private function parseAnd(): ExpressionNode
    {
        $children = [$this->parseUnary()];
        while ($this->accept('&')) {
            $children[] = $this->parseUnary();
        }

        return ExpressionNode::group(ExpressionNode::AND, $children);
    }

    private function parseUnary(): ExpressionNode
    {
        if ($this->accept('!')) {
            return ExpressionNode::not($this->parseUnary());
        }

        if ($this->accept('(')) {
            $node = $this->parseOr();
            if (! $this->accept(')')) {
                throw new InvalidArgumentException('Missing closing parenthesis in permission expression.');
            }

            return $node;
        }

        $token = $this->tokens[$this->position] ?? null;
        if ($token === null || ! preg_match('/^[a-z0-9_.]+$/', $token)) {
            throw new InvalidArgumentException('Expected a permission name, got '.($token === null ? 'end of expression' : "'{$token}'").'.');
        }
        $this->position++;

        return ExpressionNode::permission($token);
    }

    private function accept(string $token): bool
    {
        if (($this->tokens[$this->position] ?? null) === $token) {
            $this->position++;

            return true;
        }

        return false;
    }

    private function evaluateNode(ExpressionNode $node, Authenticatable $user, ?Model $record): bool
    {
        $evaluate = fn (ExpressionNode $child): bool => $this->evaluateNode($child, $user, $record);

        return match ($node->kind) {
            ExpressionNode::PERMISSION => $this->allows($node->permission ?? '', $user, $record),
            ExpressionNode::NOT => ! $evaluate($node->children[0]),
            ExpressionNode::AND => collect($node->children)->every($evaluate),
            ExpressionNode::OR => collect($node->children)->contains($evaluate),
        };
    }

    private function allows(string $permission, Authenticatable $user, ?Model $record): bool
    {
        if ($permission === self::AUTHENTICATED) {
            return true;
        }

        if (! $user instanceof Authorizable) {
            return false;
        }

        [$resource, $flag] = array_pad(explode('.', $permission, 2), 2, '');
        $readAll = $resource.'.read_all';

        $granted = $user->can($permission) || ($flag === 'read_own' && $user->can($readAll));

        if (! $granted || ! $record instanceof OwnedByUser || ! in_array($flag, self::OWNED_FLAGS, true)) {
            return $granted;
        }

        return $user->can($readAll) || $record->isOwnedBy($user);
    }
}
