<?php

declare(strict_types=1);

namespace App\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\FileTypeMapper;

/**
 * @implements Rule<Node\Expr\MethodCall>
 */
final class AllowOnlyFromRule implements Rule
{
    /**
     * @var FileTypeMapper
     */
    private $fileTypeMapper;

    public function __construct(FileTypeMapper $fileTypeMapper)
    {
        $this->fileTypeMapper = $fileTypeMapper;
    }

    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $calledOnObject = $scope->getType($node->var);
        $methodIdentifier = $node->name;
        if (!$methodIdentifier instanceof Node\Identifier) {
            return [];
        }

        $methodName = $methodIdentifier->name;
        $methodReflection = $scope->getMethodReflection($calledOnObject, $methodName);
        if ($methodReflection === null) {
            return [];
        }

        return $this->ensureMethodCanBeCalledFromHere($methodReflection, $scope);
    }

    /**
     * @return string[]
     */
    private function listAllowedCallers(MethodReflection $method): array
    {
        if ($method->getDocComment() === null) {
            return [];
        }

        $fileName = $method->getDeclaringClass()->getFileName();
        if ($fileName === false) {
            return [];
        }

        $resolvedPhpDoc = $this->fileTypeMapper->getResolvedPhpDoc(
            $fileName,
            $method->getDeclaringClass()->getName(),
            null,
            $method->getName(),
            $method->getDocComment()
        );

        $phpDocNodes = $resolvedPhpDoc->getPhpDocNodes();

        return array_reduce($phpDocNodes, function (array $allowedCallers, PhpDocNode $phpDocNode) {
            return array_reduce($phpDocNode->children, function (array $allowedCallers, PhpDocChildNode $docNode) {
                if ($docNode instanceof PhpDocTagNode && $docNode->name === '@allow-only-from') {
                    $tagValue = $docNode->value;
                    $allowedCallers[] = $tagValue->value;
                }

                return $allowedCallers;
            }, $allowedCallers);
        }, []);
    }

    /**
     * @param string[] $authorizedCallers
     */
    private function errorMessage(MethodReflection $methodReflection, array $authorizedCallers): \PHPStan\Rules\RuleError
    {
        $authorizedCallersAsString = array_reduce($authorizedCallers, function (string $authorizedCallersAsString, string $authorizedCaller) {
            $authorizedCallersAsString .= PHP_EOL . '- ' . $authorizedCaller;

            return $authorizedCallersAsString;
        }, '');

        return RuleErrorBuilder::message(sprintf('Call to %s::%s is authorized only from:%s', $methodReflection->getDeclaringClass()->getName(), $methodReflection->getName(), $authorizedCallersAsString))->build();
    }

    /**
     * @return \PHPStan\Rules\RuleError[]
     */
    private function ensureMethodCanBeCalledFromHere(MethodReflection $methodReflection, Scope $scope): array
    {
        $allowedCallers = $this->listAllowedCallers($methodReflection);

        // Because it doesn't have allowed callers we know the tag is not set.
        // It means we don't have to apply the rule for that method call.
        if (\count($allowedCallers) === 0) {
            return [];
        }

        // If the call is made outside a class we know that it's
        // made outside one of allowed callers
        if (!$scope->isInClass()) {
            return [
                $this->errorMessage($methodReflection, $allowedCallers),
            ];
        }

        if ($methodReflection->getDeclaringClass()->getName() === $scope->getClassReflection()->getName()) {
            return [];
        }

        // If we are in a class we need to check if the class
        // is one of the allowed callers
        if (!\in_array($scope->getClassReflection()->getName(), $allowedCallers)) {
            return [
                $this->errorMessage($methodReflection, $allowedCallers),
            ];
        }

        return [];
    }
}
