=======================================
Question2Answer Book
=======================================
-----------
Description
-----------
This is a plugin for **Question2Answer** that creates an html e-book of the sites top questions and answers, and provides a built-in **Book Viewer** for browsing them interactively.

--------
Features
--------

Book Generation
~~~~~~~~~~~~~~~
- fully customizable HTML template via admin/plugins
- options for sorting, inclusion exclusion filters, include/exclude answers
- option for static or on-the-fly creation (static requires PHP to write to file)
- option to create PDF file - requires wkhtmltopdf (see below)
- optional widget for displaying download links in sidebar

Book Viewer
~~~~~~~~~~~
- **Collapsible TOC sidebar** — Categories, topics, and questions in a tree structure
- **Lazy AJAX loading** — Only the selected section's HTML is fetched; the full file is never sent to the browser
- **Client-side caching** — Loaded sections are cached so repeat clicks are instant
- **Prev/Next topic navigation** — Navigate between topics with buttons
- **Tag filter** — Filter questions by tag (e.g., ``gatecse-2009``); supports multiple tags with AND logic
- **Save point** — Pin any question with 📌 to save your position (per-book, stored in localStorage)
- **MathJax rendering** — LaTeX math is rendered after each section loads
- **Code prettify** — Syntax highlighting via Google Code Prettify
- **Click-to-reveal answer keys** — Answer badge that reveals on click
- **Search/filter** — Filter sections by name from the toolbar
- **Expand/Collapse All** — Toolbar buttons to open or close the entire tree
- **Sidebar toggle** — Hamburger button to show/hide the sidebar
- **Fullscreen mode** — Browser fullscreen with sidebar still accessible
- **Dark mode** — Full dark mode support
- **Responsive** — Sidebar stacks above content on mobile
- **Multi-book support** — Drop multiple HTML files in ``html/``; a dropdown lets users switch
- **Auto-caching** — TOC structure is cached as ``.toc.json`` and auto-regenerated when the HTML changes

Lists Integration
~~~~~~~~~~~~~~~~~
- **Bookmark / Lists** — Each question title shows a ☆ button to add/remove the question from user-defined lists
- **Cross-site support** — Works across Q2A network sites; the question's origin site is auto-detected from its URL
- Requires the **Lists plugin** (``qa-lists``) to be installed; the feature is auto-detected and hidden if unavailable

Notes Integration
~~~~~~~~~~~~~~~~~
- **Self notes** — Each question title shows a ✎ button to add, edit, or delete a personal note
- **Cross-site support** — Notes are stored per-user per-question, resolved to the correct network site's DB table
- Requires the **Notes plugin** (``qa-plugin/note``) to be installed; auto-detected

Question Status Tracking
~~~~~~~~~~~~~~~~~~~~~~~~
- **Per-question status** — Mark each question as ✔ Completed, ⏭ Skipped, or ✘ Wrong via a status button on the title
- **Visual indicators** — Questions get a colored left border (green/yellow/red) matching their status
- **Filter bar** — A filter toolbar below the main toolbar lets you toggle visibility by status (All, Unmarked, Done, Skipped, Wrong)
- **Multi-select filters** — Click multiple filter buttons to show e.g. Skipped + Wrong together
- **Live counts** — Each filter button shows a count that updates in real time as you mark questions
- **Cross-site support** — Statuses are stored with the network site prefix, so they work correctly for cross-site questions
- Stored in the ``^book_question_status`` table, created automatically on first use

------------
Installation
------------
#. Install Question2Answer_
#. Get the source code for this plugin from github_, either using git_, or downloading directly:

   - To download using git, install git and then type
     ``git clone git://github.com/gateoverflow/q2a-book.git book``
     at the command prompt (on Linux, Windows is a bit different)
   - To download directly, go to the `project page`_ and click **Download**

#. Navigate to your site, go to **Admin -> Plugins** on your q2a install, go to the "Book" panel, select options, click "Create".
#. Place your generated book HTML files in ``qa-plugin/q2a-book/html/``
#. Access the Book Viewer at ``/book/``

.. _Question2Answer: http://www.question2answer.org/install.php
.. _git: http://git-scm.com/
.. _github:
.. _project page: https://github.com/gateoverflow/q2a-book

--------------
File Structure
--------------

::

  q2a-book/
  ├── qa-plugin.php              # Plugin registration (book generation + viewer)
  ├── metadata.json              # Plugin metadata
  ├── qa-book-admin.php          # Admin panel for book generation + DB table creation
  ├── qa-book-overrides.php      # Q2A overrides
  ├── qa-book-widget.php         # Sidebar widget
  ├── util-book.php              # Utilities
  ├── wkhtmltopdf.php            # PDF export
  ├── html/                      # Book HTML files (add your own here)
  │   └── *.html
  └── book-view/                 # Book Viewer module
      ├── qa-book-page.php       # Page module — renders the viewer UI and builds TOC
      ├── qa-book-ajax.php       # AJAX endpoint — section extraction, lists, notes, question status
      ├── css/
      │   └── book-viewer.css    # All styles including dark mode
      └── js/
          └── book-viewer.js     # Frontend: TOC tree, AJAX loading, search, lists, notes, status

------------
Static Files
------------

To output static files you need to find a location that is writeable by PHP.  The default is to write to the plugin dir itself, which is probably not writeable.  On Linux, something like this works:

  touch book.html
  touch book.pdf
  chmod 777 book.html book.pdf

------------
PDF File
------------

To use the PDF export, you need to put the binary of wkhtmltopdf, available here:

http://code.google.com/p/wkhtmltopdf/downloads/list

in the same directory as these plugin files.  It works for Linux 64-bit, no guarantees apart from that.

----------
Disclaimer
----------
This is **beta** code.  It is probably okay for production environments, but may not work exactly as expected.  Refunds will not be given.  If it breaks, you get to keep both parts.

-------
Release
-------
All code herein is Copylefted_.

.. _Copylefted: http://en.wikipedia.org/wiki/Copyleft

---------
About q2A
---------
Question2Answer is a free and open source platform for Q&A sites. For more information, visit:

http://www.question2answer.org/

