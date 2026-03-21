## Issue
<!--
Please add a short description of the current behavior (without your changes),
i.e. "
    When setting the `phpType` column attribute to a PHP Enum, Perpl models try to instantiate the value
    with `new`. This leads to exceptions, since Enums have to be referenced as class constants or via `::from()`.
"-->



## Changes
<!--
Please add a short description of your changes,
i.e. "
    This PR checks if `phpType` is an enum and instantiates those correctly. It affects loading from DB (hydrate()),
    writing to DB (getAccessValueStatement()) and applying default values. 
"-->



## Implementation Details
<!--
Please add anything that helps us to reason about the changes (optional),
i.e. "
    - I'm handling BackedEnum and UnitEnum as different cases, because of how different they are. Does that make sense?
    - Visibility of `FooClass::fooMethod()` was changed to public, so I can call it from `SomeOtherClass`.
"-->



## Test strategy
<!--
Your changes will have to pass static analysis, linter, and the test suite, and any added or changed behavior needs to be validated by tests, provided by your PR.

Please add a short description of the tests required to validate this PR,
i.e. "
    - Added tests to check code output as string.
    - Runtime behavior is validated by creating dummy classes with QuickBuilder.

    Not sure if this requires actual runtime tests against database?
"

 Find out more about test suite at https://perplorm.github.io/documentation/cookbook/working-with-test-suite.html
 
 You can run checks locally using the docker containers at https://github.com/perplorm/perpl-test-docker

-->



## Notes
<!--
Remove if not needed, or add what you want to discuss.

!!! Thank you for contributing to Perpl !!!

Once submitted, we'll go through a review to get your changes merged as soon as possible.
-->
