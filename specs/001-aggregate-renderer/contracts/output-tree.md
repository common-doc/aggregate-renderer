# Contract: output tree and address mapping

**Feature**: `001-aggregate-renderer` | **Plan**: [../plan.md](../plan.md)

This contract is the published surface of a build. The publish workflow uploads the output directory
as an artifact and nothing else, so every published address must be visible in this tree.

## Layout

Given `output.directory` of `dist` and `site.hostname` of `docs.example.org`:

```text
dist/
|-- CNAME
|-- index.html
|-- 404.html
`-- <slug>/
    `-- <version>/
        |-- index.html
        |-- <document>.html
        `-- <directory>/
            |-- index.html
            `-- <document>.html
```

## Rules

1. Every rendered page writes exactly one file, and that file is the only file that serves its
   address.
2. A page's file and its address are derived together from one `Target` and one document path. No
   stage writes a file without the address that names it, and no stage records an address without the
   file that backs it.
3. A document `index.md` at the root of a project's documentation directory maps to
   `<slug>/<version>/index.html` and to `https://<hostname>/<slug>/<version>/index.html`.
4. Any other document `<relative path>.md` maps to `<slug>/<version>/<relative path>.html` and to
   `https://<hostname>/<slug>/<version>/<relative path>.html`. One document in, one html file out:
   nothing becomes a directory, and a document keeps its own file name.
5. The site index is `index.html` at the output root and is served at `https://<hostname>/`.
6. The not-found page is `404.html` at the output root. It is not a page of any project, is not
   counted in `totals.pages`, and has no entry in the report.
7. `CNAME` sits at the output root, contains the configured hostname and a trailing newline, and no
   other content.
8. The output tree is rewritten from the resolved corpus on every run. A file that the current corpus
   does not produce is not present after the build, whether the build was clean or incremental.
9. No file in the tree contains a token, a commit reference used as proof of freshness, a timestamp,
   or an absolute path. Two builds of an unchanged corpus are byte-identical.
10. Every generated file uses LF endings.
11. A page's `href` to a document of the corpus is root absolute, for example
    `/slug/version/document.html`, so a page's own depth never changes what its links address.
12. A directory's `index.html` is still the page GitHub Pages serves for that directory, so
    `/slug/version/api-reference/` keeps resolving without a redirect. Only the hrefs name the file
    explicitly.
13. Navigation markup, when navigation is enabled, lists the pages of the corpus and marks the page
    being rendered as current. It is produced by the theme's
    `Webware\Navigation\Renderer\RendererInterface` implementation from the same routes the build
    registered, so it cannot disagree with the pages it links to.

## Verification

- Walk the tree and assert the file count equals the rendered page count plus three: `index.html`,
  `404.html`, `CNAME`.
- Parse every page address recorded by the build, convert it to a path, and assert the file exists.
- Assert `CNAME` exists before the tree is handed to publish; its absence is a build failure with
  reason code `missing-cname`.
