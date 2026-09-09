# VPS technical documentation

This readme serves as a collection / index for the existing VPS documentation within this repo.

## Technical status of VPS provisioning
A state diagram has been created with the current flow of the technical status of a VPS provisioning:
[state-technical-status.plantuml](state-technical-status.plantuml)

## Cloudstack Async jobs
Whenever an action in Cloudstack is asynchronous we need to poll the status of that action to know what the current state is (succeeded? still going? failed?).
Cloudstack returns a job id which we can poll to check the job status.
This is handled by a generic job: `CloudstackAsyncJob`.

Different types of async jobs (Deploy, Reinstall, Destroy, etc) can extend this class and override the pending, success or failed state.

A class diagram of the current implemented jobs can be found in the following uml:
[async-job-class-diagram.plantuml](Jobs%2Fasync-job-class-diagram.plantuml)

## Cloudstack jobs pruning
Cloudstack jobs which are older then 1 month are automatically pruned by the system.
