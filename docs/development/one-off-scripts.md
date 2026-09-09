# One-off scripts

See [confluence](https://yh-jira.atlassian.net/wiki/spaces/DEV/pages/1655373877/#Guidelines-for-the-script)
for additional guidelines about one-off scripts.

## How it works

Every one-off script that needs to be run must be made available through Nova so
that a developer can execute it by themselves.
This can be done by adding a Nova action and making it available through the setup
described [below](#class-setup).

The setup makes sure the one-off actions can be run without having to make a
selection in the one-off index page, but the action will automatically be linked
to a unique one-off record by a slug you need to define in the action. This unique
database record will be used to register the last execution date.

### Additional input required for action

If executing the script requires additional input (like a csv upload, customer id's),
you can use the Nova action fields to get those input values.

### Jobs

If the script needs to make use of a job queue then use the `default`.

## Class setup

In Nova a one-off page is made available in the main menu.
On this page an overview of (historically ran) one-off's will be shown by using
a one-off model and related Nova resource.

Overview of file locations:

- `~/src/Apps/Nova/OneOffScripts/`
  - `NovaOneOffScriptResource`
    - This is the resource connecting the model to Nova.
    - The only thing that should be edited in this resource is the list of available
action in the `actions()` function.
  - `NovaOneOffScriptAbstractAction`
    - This abstract action provides some utility functionalities. It should not be
touched for a new one-off.
- `~/src/Apps/OneOffScripts/`
  - This is where the actual one-off actions and additional scripts should be added.
  - For each one-off a separate directory should be created.
  - This will make it very easy to clean-up afterward.
- `~/tests/Apps/OneOffScripts/`
  - The location for the tests written for the one-off scripts.

## Life cycle of a one-off

### Adding a one-off
1. Create a dir for your one-off; `~src/Apps/OneOffScripts/{MyOneOff}`.
2. Create a Nova action in that directory that extends the `NovaOneOffScriptAbstractAction`.
   1. This abstract enforces you to give a unique slug and ticket ref for the one-off.
   2. Add your one-off Nova action to `NovaOneOffScriptResource::actions()` to make it
available.
3. If additional classes for the one-off are required, place them in this directory as well.

### Removing a one-off
1. You must remove the one-off in a release after it has run.
2. Remove the directory of your one-off in `~/src/Apps/OneOffScripts/` and
`~/tests/Apps/OneOffScripts/`.
3. Remove your one-off action from `NovaOneOffScriptResource::actions()`.

The one-off record in the database will stay and can be used as a reference later on.

## One-off action example

An example of a one-off action can be found in [Confluence](https://yh-jira.atlassian.net/wiki/spaces/DEV/pages/1655373877#Example-one-off-action)
