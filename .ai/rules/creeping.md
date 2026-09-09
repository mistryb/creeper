---
paths:
  - 'app/Creeping/**'
---

# Creeping

## A creep type is a whole instruction set, resolved off the enum
`CreepType::instructions()` returns the `CreepInstructions` for a target: which agent reads the page, which `DigestProfile` it is shown, where the reading is filed, and what counts as a change. `LlmCreepDriver` and `CompleteCreepRun` never branch on type themselves — they ask the instructions. Adding a type means: an enum case, an `app/Creeping/Instructions/*Instructions.php`, an agent, a `DigestProfile`, a payload object, a snapshot table + model + factory, a detector, a resource, a `latest*Snapshot` relation on `CreepTarget`, and an entry in `resources/js/lib/creep-types.ts` (all screen copy lives there, not in the enum).

Snapshots are per type; `creep_changes` is shared. Its `from_snapshot_id`/`to_snapshot_id` deliberately have no foreign keys — they point at whichever snapshot table the target's type uses. Do not "fix" that by constraining them to `product_snapshots` again.
