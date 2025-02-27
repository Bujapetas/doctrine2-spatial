<?php
/**
 * Copyright (C) 2015 Derek J. Lambert
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace CrEOF\Spatial\ORM\Query\AST\Functions;

use CrEOF\Spatial\Exception\UnsupportedPlatformException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Abstract spatial DQL function
 *
 * @author  Derek J. Lambert <dlambert@dereklambert.com>
 * @license http://dlambert.mit-license.org MIT
 */
abstract class AbstractSpatialDQLFunction extends FunctionNode
{
    /**
     * @var string
     */
    protected $functionName;

    /**
     * @var array
     */
    protected $platforms = array();

    /**
     * @var Node[]
     */
    protected $geomExpr = array();

    /**
     * @var int
     */
    protected $minGeomExpr;

    /**
     * @var int
     */
    protected $maxGeomExpr;

    /**
     * @param Parser $parser
     */
    public function parse(Parser $parser): void
    {
        $lexer = $parser->getLexer();

        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->geomExpr[] = $parser->ArithmeticPrimary();

        while (count($this->geomExpr) < $this->minGeomExpr || (($this->maxGeomExpr === null || count($this->geomExpr) < $this->maxGeomExpr) && $lexer->lookahead['type'] != TokenType::T_CLOSE_PARENTHESIS)) {
            $parser->match(TokenType::T_COMMA);

            $this->geomExpr[] = $parser->ArithmeticPrimary();
        }

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    /**
     * @param SqlWalker $sqlWalker
     *
     * @return string
     */
    public function getSql(SqlWalker $sqlWalker): string
    {
        $this->validatePlatform($sqlWalker->getConnection()->getDatabasePlatform());

        $arguments = array();
        foreach ($this->geomExpr as $expression) {
            $arguments[] = $expression->dispatch($sqlWalker);
        }

        return sprintf('%s(%s)', $this->functionName, implode(', ', $arguments));
    }

    /**
     * @param AbstractPlatform $platform
     *
     * @throws UnsupportedPlatformException
     */
    protected function validatePlatform(AbstractPlatform $platform)
    {
        $platformName = $platform->getName();

        if (isset($this->platforms) && !in_array($platformName, $this->platforms)) {
            throw new UnsupportedPlatformException(
                sprintf('DBAL platform "%s" is not currently supported.', $platformName)
            );
        }
    }
}
