# DDD Activity Bundle

The activity journal of a DDD application: **who did what, through which chain
of causes, and how it ended** — for every command, every sensitive read and
every effect on the outside world.

"Who deployed lapsa to production on the 12th?" becomes a search in the back
office: Pauline from Slack, an AI agent, or the system reacting to an approval
— with everything that led to it and everything that followed.

## Install

```bash
composer require alexandrebulete/ddd-activity-bundle
bin/console doctrine:migrations:migrate
```

Requires `alexandrebulete/ddd-symfony-bundle` ≥ 1.2, whose tracing provides the
actor and the chain on every message. Symfony Security and an IAM are
optional: without them, the system is the actor of every chain.

The bundle ships its own migration (a service, so it follows
`activity.table`): never `migrations:diff` its table.

## What gets journaled

| | Journaled |
|---|---|
| A command (`CommandInterface`) | always — unless marked `#[NotJournaled]` |
| A query (`QueryInterface`) | only when marked `#[Journaled]` — sensitive reads |
| An external effect | when the adapter records it (below) |
| Anything else on the buses | no |

Each entry keeps: when; who (kind, id, and the name *at that time*); the
action; the outcome (`succeeded`, `failed` with the error, `refused`); the
channel; the chain (`correlation_id`, `causation_id`, `message_id`); and what
the message chose to describe.

### Describing a use case

Nothing to do for the defaults. To name the subject, a readable sentence and
the facts worth keeping, a message implements the foundation's
`JournaledInterface` — the Application layer depends on nothing else:

```php
final readonly class ApproveDeployment implements CommandInterface, JournaledInterface
{
    public function describeActivity(): ActivityDescription
    {
        return new ActivityDescription(
            subjectType: 'mission',
            subjectId: (string) $this->missionId,
            summary: 'mission.deployment_approved',   // translation key
            summaryParams: ['release' => $this->release],
            details: ['release' => $this->release],
        );
    }
}
```

Details are scalars or lists of scalars, picked explicitly: nothing is
serialized implicitly, so no secret, entity or client document slips in.

### Recording an external effect

```php
$activityJournal->recordEffect(
    'github.deployment_triggered',
    new ActivityDescription(subjectType: 'mission', subjectId: $missionId),
);
```

The effect joins the chain being handled. It is written on its own
connection: an effect that happened stays journaled even if the transaction
around it is rolled back.

## Guarantees

- **A success is written in the command's transaction**: rolled back with it,
  the journal never claims what did not happen. The EntityManager is flushed
  first, so a failure at flush time (a constraint) is a failure.
- **A failure is written on a second connection**: it survives the rollback,
  and a closed EntityManager.
- **Journaling never hides an error**: an entry that cannot be written is
  logged, and the original exception is what surfaces.
- **Once per message**: the middleware sits right before the handling — a
  message sent to a transport is journaled where it is consumed.

**Databases.** PostgreSQL and MySQL get every guarantee above. SQLite has a
single writer: a failure occurring *after* the command wrote cannot be
journaled — it is logged instead, without ever blocking the application.

## Back office

With Sylius Admin UI, "Activity journal" lists entries with filters (actor,
action, subject, outcome, chain). "Show the chain" narrows the list to one
chain, oldest first. Read-only by construction.

## Retention

```bash
bin/console activity:purge    # entries older than activity.retention_days
```

The purge is a command like any other: the journal records its own purges.

## Configuration

```yaml
activity:
    table: activity_log        # default
    retention_days: 730        # default: 2 years
    admin:
        enabled: true          # false = no Sylius screen (headless)
        grid_limits: [25, 50, 100]
```

## Development

```bash
composer install
composer qa    # phpstan (max + strict rules), deptrac, phpunit
```

The integration tests boot a kernel without Sylius nor Security, on the
database given by `DDD_TEST_DATABASE_URL` (a SQLite file otherwise); CI runs
them on PostgreSQL, MySQL and SQLite.
