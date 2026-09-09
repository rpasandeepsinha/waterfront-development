# Commands and cronjob standards

When creating or refactoring a command there are a few standards Sandwave
has agreed on.

## Dry-run

Every CUD command should contain a dry-run option which should be true by default.
If you think a dry-run is not necessary please consult #Team-infra.
This is to prevent data manipulation on accident. This is also very useful
to test if the results matches your expectation.

## Output

It is a nice to have to create a csv with your expected results or your actual results so that you can
check whether the job had it's desired effect.

## Queue jobs for data manipulation

When doing data manipulation the actual data manipulation should
happen in a job which should be dispatched on the corresponding queue.


## Queue Selection

Consider using one of the pre-created queues (*config/horizon.php*). If you think it needs a separate queue
please consult #Team-Infra because making multiple queue's without proper balancing
can cost more resources than one queue.

## No Interaction

Laravel commands allow you to have interaction with the executing user.
This should **NOT** be done. If you really need interaction please
consider contacting #Team-Infra.

## Tests
**Every command should be tested appropriately**

## Command output
Consider implementing a progress bar where this is appropriate so that anyone running a command can track the progress instead of staring at a blinking cursor.

All output of a command must be logged using the PSR2 `LoggerInterface` so the output will be available
even days after the command was run.
