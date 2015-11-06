
# Bookmeka

An Omeka plugin for books (odt, tei, epub)

## Install

Still for developpers only.

Download working sources with submodules in your omeka plugins/ directory.
```
$ cd {myOmeka/}plugins/
  
# for git 1.6.5+, option recursive to obtain submodules
$ git clone --recursive https://github.com/oeuvres/Bookmeka.git
  
# for git < 1.6.5 (not yet tested)
$ git clone https://github.com/oeuvres/Bookmeka.git
$ cd Bookmeka
$ git submodule update --init --recursive
```

Other site configuration

 * [Obeka admin interface] http://{mydomain.net/myOmeka/}admin/: Settings / Security / Disable File Upload Validation
 * [Omeka configuration file] file:///{myOmeka/}application/config/config.ini uncomment upload.maxFileSize = "10M"

## Roadmap

 - TODO (in order of priority)
   - CsvImport of tei or odt
   - batch regeneration (ex: to restore all transformations)
   - collect Consortium CAHIER needs and requests
   - id policy and url routes
   - better graphic integration with Omeka themes
   - validation report and online help for metas
   - support for images from odt files
   - support for images from zipped tei
   - plugin options for export formats
   - default search engine
   - integration of omeka items (images) in TEI
  - MAYDO
   - epub ingestion
   - advanced search engine with lemmas
 - DONE
   - tei > Dublin Core insertion
   - odt > tei
   - tei > epub
   - tei > markdown
   - tei > iramuteq
   - tei > toc and html fragments
   - public display
   - mechanism to extend TEI suppport (an XSL Transformation pilot, can override default behaviors)

