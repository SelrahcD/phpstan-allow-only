<?php

declare(strict_types=1);

namespace App\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

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
        $caller = $scope->getType($node->var);
        $methodIdentifier = $node->name;
        if (!$methodIdentifier instanceof Node\Identifier) {
            return [];
        }

        $methodName = $methodIdentifier->name;

        if ($caller instanceof UnionType) {
            return array_reduce($caller->getTypes(), function (array $errors, Type $type) use ($methodName, $scope) {
                return array_merge($errors, $this->ensureMethodCanBeCalledFromHere($type, $methodName, $scope));
            }, []);
        }

        return $this->ensureMethodCanBeCalledFromHere($caller, $methodName, $scope);
    }

    /**
     * @return string[]
     */
    private function listAllowedCallers(ObjectType $caller, string $methodName, Scope $scope): array
    {
        if (
            $caller->getMethod($methodName, $scope)->getDocComment() === null
        ) {
            return [];
        }

        $resolvedPhpDoc = $this->fileTypeMapper->getResolvedPhpDoc(
            $caller->getClassReflection()->getFileName(),
            $caller->getClassName(),
            null,
            $methodName,
            $caller->getMethod($methodName, $scope)->getDocComment()
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
    private function errorMessage(string $className, string $methodName, array $authorizedCallers): \PHPStan\Rules\RuleError
    {
        $authorizedCallersAsString = array_reduce($authorizedCallers, function (string $authorizedCallersAsString, string $authorizedCaller) {
            $authorizedCallersAsString .= PHP_EOL . '- ' . $authorizedCaller;

            return $authorizedCallersAsString;
        }, '');

        return RuleErrorBuilder::message(sprintf('Call to %s::%s is authorized only from:%s', $className, $methodName, $authorizedCallersAsString))->build();
    }

    /**
     * @return array|\PHPStan\Rules\RuleError[]
     */
    private function ensureMethodCanBeCalledFromHere(Type $caller, string $methodName, Scope $scope): array
    {
        // Always allow calls from inside the class
        if ($caller instanceof ThisType) {
            return [];
        }

        if (!$caller instanceof ObjectType) {
            return [];
        }

        $allowedCallers = $this->listAllowedCallers($caller, $methodName, $scope);

        // Because it doesn't have allowed callers we know the tag is not set.
        // It means we don't have to apply the rule for that method call.
        if (\count($allowedCallers) === 0) {
            return [];
        }

        // If the call is made outside a class we know that it's
        // made outside one of allowed callers
        if (!$scope->isInClass()) {
            return [
                $this->errorMessage($caller->getClassName(), $methodName, $allowedCallers),
            ];
        }

        // If we are in a class we need to check if the class
        // is one of the allowed callers
        if ($scope->isInClass() && !\in_array($scope->getClassReflection()->getName(), $allowedCallers)) {
            return [
                $this->errorMessage($caller->getClassName(), $methodName, $allowedCallers),
            ];
        }

        return [];
    }
}
