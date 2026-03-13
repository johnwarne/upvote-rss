# Change Log
All notable changes to this project will be documented in this file.

## [v3.3.3](https://github.com/fivefilters/readability.php/releases/tag/v3.3.3)
- Fix type error - extends type support to add DOMProcessingInstruction in more method signatures (reported by @reinierkors)

## [v3.3.2](https://github.com/fivefilters/readability.php/releases/tag/v3.3.2)
- Fix type error - extends type support to include DOMCdataSection and DOMProcessingInstruction in various method signatures (reported by @mikiescolarmrf and @Grotax)

## [v3.3.1](https://github.com/fivefilters/readability.php/releases/tag/v3.3.1)
- Fix DOMProcessingInstruction errors

## [v3.3.0](https://github.com/fivefilters/readability.php/releases/tag/v3.3.0)
- Fixed PHP 8.4 deprecation warning (reported by @pich)
- Migrated type declarations from PHPDoc blocks to native PHP 8 property and method types
- Empty class attributes now removed when `keepClasses` is disabled
- Replaced legacy DOM operations with native PHP 8 methods:
  - `isWhitespaceInElementContent()` for whitespace detection
  - `firstElementChild` and `previousElementSibling` for DOM traversal
- Updated Docker test environment to support PHP 8.1-8.4

## [v3.2.0](https://github.com/fivefilters/readability.php/releases/tag/v3.2.0)
- Update dependencies to newer versions (League/URI version 7), to make it compatible with projects already relying on those versions
- Minimum PHP version set to 8.1 (required by League/URI 7)
- Update Docker tests to use PHP 8.1, 8.2 and 8.3

## [v3.1.7](https://github.com/fivefilters/readability.php/releases/tag/v3.1.7)
- Fixes URL syntax errors when bad URLs are encountered when rewriting relative URLs - reported by @marcelklehr
- Fixes PHP 8 deprecation notice when base URLs (used for rewriting relative URLs) don't have a path component - thanks to @blat and @Markus-GS

## [v3.1.6](https://github.com/fivefilters/readability.php/releases/tag/v3.1.6)
- Avoid re-parsing source HTML when making multiple attempts to identify content in parse()

## [v3.1.5](https://github.com/fivefilters/readability.php/releases/tag/v3.1.5)
- Allow psr/log version 2.x and 3.x - thanks to @piotrek-r and @ArondeParon

## [v3.1.4](https://github.com/fivefilters/readability.php/releases/tag/v3.1.4)
- Fixes improper use of null coalescing operator - reported by @thedf

## [v3.1.3](https://github.com/fivefilters/readability.php/releases/tag/v3.1.3)
- Fixes issue where exception was thrown when resolving an invalid relative URL (when setFixRelativeURLs(true)) - reported by @jeffbotw

## [v3.1.2](https://github.com/fivefilters/readability.php/releases/tag/v3.1.2)
- Fixes issue "Warning: Undefined array key 2" reported by @castroCrea
- Fixes issue "Notice: Trying to get property '' of non-object" reported by @thedf

## [v3.1.1](https://github.com/fivefilters/readability.php/releases/tag/v3.1.1)
- Exclude tests folder when using composer

## [v3.1.0](https://github.com/fivefilters/readability.php/releases/tag/v3.1.0)
- Minimum PHP version 7.4 (composer.json updated)
- Updated the Docker file to support versions of PHP from 7.4 to 8.1
- Updated the Docker file to allow you to run PHP with libxml 2.9.10, 2.9.13, 2.9.14
- Test with PHP 8.1

## [v3.0.0](https://github.com/fivefilters/readability.php/releases/tag/v3.0.0)
- Implemented changes made to Readability.js up to 26 August 2021, with the exception of a [piece of code](https://github.com/fivefilters/readability.php/commit/1c662465bded2ab3acf3b975a1315c8c45f0bf73#diff-b9b31807b1a39caec18ddc293e9c52931ba8b55191c61e6b77a623d699a599ffR1899) which doesn't produce the same results in PHP for us compard to the JS version.
- Default parser is now HTML5-PHP, which handles HTML better than libxml
- Replaced the expected HTML files in the tests folder to reflect HTML5-PHP's serialisation
- Updated the Docker file to support versions of PHP from 7.3 to 8.0 (previously it was 7.0 to 7.3)
- Updated the Docker file to allow you to run PHP with libxml 2.9.4, 2.9.5, 2.9.10, and 2.9.12
- Fatal error bug fix (thanks Balazsp)

## [v2.1.0](https://github.com/andreskrey/readability.php/releases/tag/v2.1.0)
- Avoid overwriting extracted metadata with similarly named keys (like `og:image` and `og:image:width`)
- Imported new `getSiteName()` feature from JS version as of [21 Dec 2018](https://github.com/mozilla/readability/pull/504)
- Added getFirstElementChild function to NodeTrait + test case (Issue #83)
- Reworked the test suit to use TestPage objects and give more hints about what failed
- Removed getWordThreshold and setWordThreshold configuration functions
- Added NodeUtility::filterTextNodes and deprecated NodeTrait getChildren()
- Added new DOMNodeList fake class that mimics the original DOMNodeList class but allows to add new nodes to the list
- Added new Dockerfiles that pulls different versions of PHP and libxml. Now we are supporting 4 versions of PHP and 6 versions of libxml!

## [v2.0.1](https://github.com/andreskrey/readability.php/releases/tag/v2.0.1)
- Fixed small issue that prevented the main image from showing up in the results

## [v2.0.0](https://github.com/andreskrey/readability.php/releases/tag/v2.0.0)

- [BREAKING CHANGE] Bumped the minimum supported version of PHP to 7.0
- Clean `<aside>` tags during `prepArticle()`.
- Merged PR #58: Fix notice non-object on $parentOfTopCandidate for tumblr.com
- Fixed issue #63: Division by zero
- Housekeeping:
    - Removed $parseSuccessful flag that wasn't needed anymore
- Rename wordThreshold to charThreshold and throw deprecation notices. WordThreshold will be removed in version 3.0.
- Added "-ad-" as unlikely candidate
- Added Docker containers with PHP 7.0, 7.1, and 7.2 and makefile to trigger the tests.
- Imported new code from the JS version as of [19 Nov 2018](https://github.com/mozilla/readability/commit/876c81f710711ba2afb36dd83889d4c5b4fc2743), which includes the following changes:
    - Move phrasing contents [into paragraphs](https://github.com/mozilla/readability/commit/9f2c5cb42ee9635f091178271d66888cbb47e5dc)
    - Improved the title detection
    - Remove [single cell tables](https://github.com/mozilla/readability/commit/ea4165721f9105d8f1e53cfecdcfdafceaf3e4bf)
    - Improved the detection of video related elements
    - New test cases
    - Various minor fixes


## [v1.2.0](https://github.com/andreskrey/readability.php/releases/tag/v1.2.0)

- Merged PR#49 (Missing object when calling `->getContent()`)
- Imported all changes from Readability.js as of 2 March 2018 ([8525c6a](https://github.com/mozilla/readability/commit/8525c6af36d3badbe27c4672a6f2dd99ddb4097f)):
    - Check for `<base>` elements before converting URLs to absolute.
    - Clean `<link>` tags on `prepArticle()`
    - Attempt to return at least some text if all the algorithm runs fail (Check PR [#423](https://github.com/mozilla/readability/pull/423) on JS version)
    - Add new test cases for the previous changes
    - And all other changes reflected [in this diff](https://github.com/mozilla/readability/compare/c3ff1a2d2c94c1db257b2c9aa88a4b8fbeb221c5...8525c6af36d3badbe27c4672a6f2dd99ddb4097f)

## [v1.1.1](https://github.com/andreskrey/readability.php/releases/tag/v1.1.1)

- Switched from assertEquals to assertSame on unit testing to avoid weak comparisons.
- Added a safe check to avoid sending the DOMDocument as a node when scanning for node ancestors.
- Fix issue #45: Small mistake in documentation
- Fix issue #46: Added `data-src` as a image source path
- Fixed bug when extracting all the image of the article (Was extracting images from the original DOM instead of the parsed one)
- Added the `->getDOMDocument()` getter to retrieve the fully parsed DOMDocument
- Merged PR #48 that allows passing an array as configuration (@topotru)

## [v1.1.0](https://github.com/andreskrey/readability.php/releases/tag/v1.1.0)

- Added 'data-orig' as an URL source for images
- Removed 'modal' as a negative property from classes
- Added option to inject a logger
- Removed all references to the `data-readability` tags that don't apply anymore to the new structure
- Merged PR #38 (Missing DOMEntityReference)

## [v1.0.0](https://github.com/andreskrey/readability.php/releases/tag/v1.0.0)

- Node encapsulation is gone. Pre v1 all nodes where encapsulated in a Readability class, which created lots of trouble with dependencies, responsibilities, and properties. Now all the encapsulation is gone: all the DOMNodes inside the Readability class are extensions of the original DOM classes, which allows the system to take advantage of the functions and properties of DOMDocument.
- HTMLParser is gone, Readability is the new main class. Switched things a bit for this release. Pre v1 you had to create an HTMLParser class to parse the HTML. Now you have to create a Readability class, feed it the text, and check the result.
- No more dumb arrays as a result. If you want to get the title, content, images, or anything else you'll have to use the getters of the Readability class.
- Environment class is gone. Now you have to create a configuration class and use setters to set your configuration options.
- Exceptions. Make sure you wrap your Readability class in a try catch block, because if it fails to parse your HTML, it will throw a `ParseException`.
- Minimum PHP version bumped to 5.6.

## [v0.3.1](https://github.com/andreskrey/readability.php/releases/tag/v0.3.1)

- Trim titles when detecting hierarchical separators to avoid false negatives on strings with spaces.
- Fix issue when converting divs to p nodes and never rating them (issue #29)
- Fix "Unsupported operand types" (PR #31)
- Fix division by zero when no title was found (issue #32)
- New function to retrieve all images at once (PR #30)
- Get the title from the `<title>` tag before searching on the `<meta>` tags

## [v0.3.0](https://github.com/andreskrey/readability.php/releases/tag/v0.3.0)

- Merged PR #24. Fixes notice when trying to extract `og:image`
- Up to date to commit [eb221c5](https://github.com/mozilla/readability/commit/c3ff1a2d2c94c1db257b2c9aa88a4b8fbeb221c5) (2017-10-16), which includes the following changes:
  - New tags added to the unlikelyCandidates regex
  - Detection and removal of hierarchical separators in titles
  - Added more tags to clean after parsing the article (`button`, `textarea`, `select`, etc.)
  - New way to detect empty nodes (including a edge case where a node with a `&nsbp;` was detected as a node with content)
  - Better approach to find a top candidate (specially when a top candidate is the only child of a parent node, which allows a more accurate joining of sibling elements)
  - Detect text direction (`ltr` or `rtl`)
  - Detect and mark data tables to avoid removing them during final clean up
  - Major fixes when scanning and deleting nodes (no need to traverse backwards anymore)
  - Node cleaning via regex matches
  - Clean table attributes during final clean up.
- Added license

Next release after this one will be v1 and will be a major refactor around Readability and HTMLParser methods and responsibilities.

## [v0.2.2](https://github.com/andreskrey/readability.php/releases/tag/v0.2.2)

- Added a safecheck for really nasty HTML
- Added summonCthulhu option, to remove all script tags via regex

## [v0.2.1](https://github.com/andreskrey/readability.php/releases/tag/v0.2.1)

- Added `normalizeEntities` flag to convert UTF-8 characters to its HTML Entity equivalent. Fixes bugs on htmls with mixed encoding.
- Added more information to the readme.md file
- New way to create a backup DOM: not creating a backup. In the previous version, the system cloned the $this->dom object to keep it as a backup in order to restart the algorithm with other flags, if needed. This seemed to work until I realized that *sometimes* the backup changes even if we are not touching it. Seems that the `dom` and `backupdom` objects are linked and *some* changes on the dom object reach the bakcupdom object. The new approach consists in deleting the backupdom object and recreating from scratch the dom object. Of course this has a performance impact, but seems to be quite low.

## [v0.2.0](https://github.com/andreskrey/readability.php/releases/tag/v0.2.0)

100% complete port of Readability.js!
- Every test unit passes
- Readability.php produces the same exact output as Readability.js
- I'm happy :)

### Fixed
- Lots of bugs
- Merged PR by DavidFricker to avoid exceptions while grabbing the document content

### Added
- substituteEntities flag, to avoid replacing especial characters with HTML entities. There's nothing we can do about `&nbsp;`, that entity is replaced by libxml and there's no way to disable it.
- Named data sets so it's easier to detect which test case is failing.

### Removed

- Couple of test cases that involved broken JS. There's nothing we can do about JS spilling onto the text.

## [0.0.3-alpha](https://github.com/andreskrey/readability.php/releases/tag/v0.0.3v-alpha)

We are getting closer to be a 100% complete port of Readability.js!
- Added prepArticle to remove junk after selecting the top candidates.
- Added a function to restore score after selecting top candidates. This basically works by scanning the data-readability tag and restoring the score to the contentScore variable. This is an horrible hack and should be removed once we ditch the Element interface of html-to-markdown and start extending the DOMDocument object.
- Switched all strlen functions to mb_strlen
- Fixed lots of bugs and pretty sure that introduced a bunch of new ones.

## [0.0.2-alpha](https://github.com/andreskrey/readability.php/releases/tag/v0.0.2-alpha)
 - Last version I'm using master as the main development branch. All unreleased changes and main development will happen in the develop branch.

## [0.0.1-alpha](https://github.com/andreskrey/readability.php/releases/tag/v0.0.1-alpha)
 - Initial release
