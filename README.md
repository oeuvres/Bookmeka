
# Bookmeka

An Omeka plugin for books (odt, tei, epub)

## Install

interface: Admin / Settings / Security / Disable File Upload Validation
file: application/config/config.ini uncomment upload.maxFileSize = "10M"
(php.ini)

## Configuration params

 * tmp dir where to write produced files
 * 

## Roadmap

 - DONE
   - tei > Dublin Core insertion
   - odt > tei
   - tei > epub
   - tei > markdown
   - tei > iramuteq
   - tei > toc and html fragments
   - public display
 - TODO
   - collect Consortium CAHIER needs and requests
   - id policy and url routes
   - better graphic integration with Omeka themes
   - omeka item images in TEI
   - validation report and online help for metas
   - support for images from odt files
   - support for images from zipped tei
   - batch regeneration
   - plugin options for export formats
   - mechanism to extend TEI suppport
   - CsvImport of tei, odt, epub 
   - default search engine
  - MAYDO
   - epub ingestion
   - advanced search engine with lemmas

