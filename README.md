# Allow-only-from PHPStan Rule

The rule is in [src/Rules](./src/Rules).

An example of what I'm trying to achieve is in [src/Domain](./src/Domain).

Running PhpStan shows the expected error triggered by a call made to `Player::addGame` from
[SomeOtherAggregate](./src/Domain/SomeOtherAggregate.php).

# Questions
* What can be improved in the writing of the rule?
* What can be improved in the way the rule is tested?
* What can be improved regarding the usage of PhpStan?
