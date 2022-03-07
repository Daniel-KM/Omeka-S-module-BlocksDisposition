Blocks Disposition (module for Omeka S)
=======================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Blocks Disposition] is a module for [Omeka S] that allows to manage automatic
display of features of the modules in the resource pages.

For example, it can display the viewer [Mirador] or [Universal Viewer], then the
citation provided by the module [Citation], then the map of the module [Mapping].
then the tagging form of the module [Folksonomy], then the comments of the
module [Comment], etc. Without this module, these features would be displayed in
the alphabetic order, so the theme would be edited to get the same result.

So, this module simplifies the creation of the five main public views (`item/show`,
`item/browse`, `item-set/show`, `item-set/browse`, `media/show`), and make them
reorderable directly in the admin interface, like the simple pages.


Installation
------------

Uncompress files and rename module folder `BlocksDisposition`. Then install it
like any other Omeka module and follow the config instructions.

See general end user documentation for [Installing a module].


Usage
-----

The options can be set differently for each site in the site settings, under
`Blocks Disposition`. Simply enable and set the order of each feature for each
type of page.

The process uses the trigger `view.show.before` to reorder the modules that use
the trigger `view.show.after`, so they must not be removed from the theme. It is
the same for the triggers `view.browse.before` and `view.browse.after`.

Some modules may not be listed: add them in the module [config/module.config.php].
This module is not yet enable to determine all modules that are used in the
public resources pages


Compatibility warning
---------------------

Some module blocks are not sortable, because they use closures instead of a
simple object/method callable, so the module doesn’t know the initial module.

So to make them orderable, closures should be replaced by raw callable. For
example, for module [Mapping], the method `attachListeners()` in the main file
of the module `modules/Mapping/Module.php` must be updated:
```diff
101      $sharedEventManager->attach(
102          'Omeka\Controller\Site\Item',
103          'view.show.after',
104--            function (Event $event) {
105--                echo $event->getTarget()->partial('mapping/index/show');
106--            }
   ++            [$this, 'handleViewShowAfterItem']
107      );

Add:

    public function handleViewShowAfterItem(Event $event)
    {
        echo $event->getTarget()->partial('mapping/index/show');
    }
```

This process creates a cleaner code too, since loading step is not mixed with
the display step in the same method. This fix is the same for any module that
uses a closure for the public trigger `view.show.after`, for example [Collecting].

Another point to check is the fact that the namespace of the callable that is
triggered must be the same than the module name. This is always the case for all
known public modules.

**NOTE** This fix is integrated upstream in last version of Mapping, Collecting,
and some other modules, so you only have to check your own modules.


TODO
----

- [ ] Determine the list of modules automatically via the events they listen.
- [ ] Manage events "view.layout" so appended blocks can be prepended and vice-versa.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Copyright
---------

* Copyright Daniel Berthereau, 2019-2022 (see [Daniel-KM] on GitLab)


[Blocks Disposition]: https://gitlab.com/Daniel-KM/Omeka-S-module-BlocksDisposition
[Omeka S]: https://omeka.org/s
[Universal Viewer]: https://gitlab.com/Daniel-KM/Omeka-S-module-Mirador
[Mirador]: https://gitlab.com/Daniel-KM/Omeka-S-module-UniversalViewer
[Citation]: https://gitlab.com/Daniel-KM/Omeka-S-module-Citation
[Mapping]: https://github.com/omeka-s-modules/Mapping
[Folksonomy]: https://gitlab.com/Daniel-KM/Omeka-S-module-Folksonomy
[Comment]: https://gitlab.com/Daniel-KM/Omeka-S-module-Comment
[Collecting]: https://github.com/omeka-s-modules/Collecting
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[config/module.config.php]: https://gitlab.com/Daniel-KM/Omeka-S-module-BlocksDisposition/blob/master/config/module.config.php#L26-L96
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-BlocksDisposition/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
