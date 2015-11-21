
# Bookmeka

An Omeka plugin for books (odt, tei, epub)

## Install

Still for developpers only.

Download working sources with submodules in your omeka plugins/ directory.
```
$ cd {myOmeka/}plugins/
  
# for git 1.6.5+, option recursive to obtain submodules
$ git clone --recursive https://github.com/oeuvres/Bookmeka.git
# set branch of modules for easier update
$ git submodule foreach --recursive git checkout master
  
# for git < 1.6.5 (not yet tested)
$ git clone https://github.com/oeuvres/Bookmeka.git
$ cd Bookmeka
$ git submodule update --init --recursive
```

Pull from github
```sh
# update Bookmeka root
$ git pull
# update submodule
$ git submodule foreach --recursive git pull
```

Push to github
```sh
# Commit your local changes and push it to remote
$ git commit
$ git push
# Do no modify submodules in Bookmeka context (or be a “git guru” and fill this tuto)
```


Other site configuration

 * [Omeka admin interface] http://{mydomain.net/myOmeka/}admin/: Settings / Security / Disable File Upload Validation
 * [Omeka configuration file] file:///{myOmeka/}application/config/config.ini uncomment upload.maxFileSize = "10M"

## Roadmap

 - TODO (in order of priority)
   - default search engine
   - id policy and url routes
   - better graphic integration with Omeka themes
   - collect Consortium CAHIER needs and requests
   - validation report and online help for metas
   - support for images from odt files
   - support for images from zipped tei
   - integration of omeka items (images) in TEI
   - batch regeneration (ex: to restore all transformations)
  - MAYDO
   - epub ingestion
   - advanced search engine with lemmas
 - DONE
   - tei > Dublin Core insertion
   - tei > toc and html fragments
   - odt > tei
   - tei > epub
   - tei > markdown
   - tei > iramuteq
   - public display
   - mechanism to extend TEI suppport (an XSL Transformation pilot, can override default behaviors)
   - plugin options for export formats
   - CsvImport of tei or odt

