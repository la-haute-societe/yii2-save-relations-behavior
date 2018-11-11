# Yii2 Active Record Save Relations Behavior Change Log

## [1.7.1]
### Fixed
- Fix #23: Relational data was not loaded for nested relational models (thx @toreonify)

## [1.7.0]
### Added
- Enh #42: Add the ability to retrieve old relations values
- Enh #43: Add the ability to retrieve and mark dirty relations

## [1.6.0]
### Fixed
- Bug #36: Fix an issue with HasMany relations with composite keys (thx @leandrogehlen)
- Bug #41: Fixes error loading `hasMany` relation without referenced key in data (thx @leandrogehlen)
- Fix for transaction being started during the `beforeValidate` event (thx @leandrogehlen)

### Added
- Enh #37: Add a `relationKeyName` property to determine the key used to load relations data.

### Changed
- Removed `isModelTransactional` protected method. Transactions are not started by the behavior any more.

## [1.5.2]
### Fixed
- Fix a regression in Has One saving introduced by #30 fix.

## [1.5.1]
### Fixed
- Bug #33: Custom relation scenario was set too late and was preventing attributes from being correctly set (thx @phrakon)

## Added
- New method `setRelationScenario` can set a relation scenario at runtime

## Changed
- Light refactoring
- Updated documentation

## [1.5.0]
### Added
- Enh #5: Ability to automatically delete related records along with the main model

### Fixed
- Bug #30: HasOne relation saving issue (thx @phrakon)
- Fix for SaveRelationTrait (thx @leandrogehlen)

### Changed
- Some code refactoring
- Yii2 requirements raised to 2.0.14

## [1.4.1]
### Fixed
- Bug #24: Fix a regression introduced in 1.4.0 release where validation of hasOne relations was not triggered. (thx @dabkhazi)

## [1.4.0]
### Fixed
- Bug #25: Fix for Yii 2.0.14 compatibility. Has many relations were not saved. (thx @SanChes-tanker)

### Added
- Enh #15: Allow to save extra columns to junction table (thx @sspat)

## [1.3.2]
### Fixed
- Bug #22: Fix for HasOne relation pointing to the owner primary key (thx @mythicallage)

## [1.3.1]
### Added
- Enh #19: Support for defining relations through the `getRelation` method (thx @execut)
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
