# Yii2 Active Record Save Relations Behavior Change Log

## [1.3.1] under development
### Added
- Enh #21: Better support of partial composite keys (thx @execut)

## [1.3.0]
### Added
- Enh #3: Ability to define validation scenario for related records
- Enh #7: Exception logging during `beforeValidate` and `afterSave` events.
- More test cases

### Fixed
- False positive `testLoadRelationsShouldSucceed` test case

### Changed
- afterSave throw exception if a related record fail to be saved. In that case, a database rollback is triggered (when relevant) and an error is attached to the according relation attribute 
- related record are now correctly updated based on there primary key (Thanks to @DD174)

## [1.2.0]
### Changed
- Use of `ActiveQueryInterface` and `BaseActiveRecord` to ensure broader DB driver compatibility (Thx @bookin)

## [1.1.3] 2017-03-04
### Fixed
- Bug #13: Relations as array were inserted then deleted instead of being updated


## [1.1.2] 2016-11-29
### Fixed
- Fix for empty value assignment on HasMany relations

## [1.1.1] 2016-11-28
### Fixed
- Bug #12: Nesting level too deep – recursive dependency error during object comparing (Thx @magicaner)

## [1.1.0] 2016-04-03
### Added
- SaveRelationsTrait to load related data using the `load()` method
- ability to set HasMany relation using a single object (Thanks to @sankam-nikolya and @k0R73z))

## [1.0.2] 2016-04-03
### Fixed
- Fix a bug that was preventing new records to be correctly saved for hasMany relations.

## [1.0.1] 2016-04-02
### Fixed
- Setting null for a named relation is now correctly handled.
- A new related model instance was previously generated instead.

## [1.0.0] 2016-03-26
Initial release