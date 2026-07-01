<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A binary operator expression (e.g. `a = b`, `a + b`).
 *
 * Operands are wrapped in parentheses based on operator precedence so the
 * generated SQL preserves the intended grouping.
 */
final class OpExp extends ExpBase implements Precedencer
{
    public function __construct(
        private readonly Exp $lft,
        private readonly string $op,
        private readonly Exp $rgt,
        private readonly bool $unspaced = false,
        private readonly ?Requirement $requires = null,
    ) {
    }

    public function precedence(): int
    {
        return Precedence::of($this->op);
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($this->requires !== null) {
            $sb->requireAnyDialect('the ' . $this->op . ' operator', $this->requires);
        }

        $lftNeedsParens = $this->lft instanceof Precedencer && $this->lft->precedence() < $this->precedence();

        if ($lftNeedsParens) {
            $sb->writeString('(');
        }
        $this->lft->writeSql($sb);
        if ($lftNeedsParens) {
            $sb->writeString(')');
        }

        $sb->writeString($this->unspaced ? $this->op : ' ' . $this->op . ' ');

        $rgtNeedsParens = false;
        if ($this->rgt instanceof Precedencer) {
            $rgtNeedsParens = $this->rgt->precedence() < $this->precedence();
            // Special case: if the right expression is an op expression with a
            // different operator but the same precedence (e.g. + / -), we need
            // parentheses.
            if ($this->rgt instanceof OpExp && $this->rgt->op !== $this->op && $this->rgt->precedence() === $this->precedence()) {
                $rgtNeedsParens = true;
            }
        }

        if ($rgtNeedsParens) {
            $sb->writeString('(');
        }
        $this->rgt->writeSql($sb);
        if ($rgtNeedsParens) {
            $sb->writeString(')');
        }
    }
}
