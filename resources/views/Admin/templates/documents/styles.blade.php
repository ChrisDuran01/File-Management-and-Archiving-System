{{-- Look of a template document, shared by the PDF (Admin/templates-pdf)
     and the live preview on Admin/templates. Sizes are in em so the
     preview can be smaller than the page. Rules only - include it inside
     a style element. --}}
.doc-sheet { font-family:'Bookman Old Style', 'DejaVu Serif', Georgia, serif; line-height:1.3; color:#111; }
.doc-sheet p { margin:0 0 .75em; }
.doc-sheet sup { font-size:.65em; line-height:0; }
.doc-sheet .b { font-weight:bold; }

/* Plain text templates (DocumentFormatter) */
.doc-sheet .doc-heading { text-align:center; font-weight:bold; margin:0 0 .5em; }
.doc-sheet .doc-title { text-align:center; font-weight:bold; margin:.3em 0 1.3em; }
.doc-sheet .doc-meta { margin:0 0 .35em; }
.doc-sheet .doc-clause { text-align:justify; text-indent:3em; }
.doc-sheet .doc-paragraph { text-align:justify; }
.doc-sheet .doc-empty { color:#777; font-style:italic; }

/* Resolution */
.doc-sheet .res-number, .doc-sheet .res-series { text-align:center; font-style:italic; margin:0; }
.doc-sheet .res-series { margin-bottom:1.3em; }
.doc-sheet .res-title { text-align:center; font-weight:bold; padding-bottom:.15em; border-bottom:1.5pt solid #111; }
.doc-sheet .res-author { text-align:center; padding:.15em 0; border-bottom:1.5pt solid #111; margin-bottom:1.5em; }
.doc-sheet .clause { text-align:justify; }
.doc-sheet .res-vote { text-align:center; font-style:italic; margin:1.8em 0 1em; }

/* Signatures */
.doc-sheet .sign-group { margin-top:.9em; page-break-inside:avoid; }
.doc-sheet .sign-label { text-align:center; margin:0 0 .4em; }
.doc-sheet .sign-label.strong { font-weight:bold; text-transform:uppercase; }
.doc-sheet table.signers { width:100%; border-collapse:collapse; }
.doc-sheet table.signers td { width:50%; text-align:center; vertical-align:top; padding:0 .3em .8em; }
.doc-sheet .sign-left .sign-label, .doc-sheet .sign-left table.signers td { text-align:left; }
.doc-sheet .signer-space { height:2.2em; }
.doc-sheet .signer-name { font-weight:bold; }
.doc-sheet .signer-position { font-style:italic; }

/* Request letter */
.doc-sheet .letter-date { margin-bottom:1.6em; }
.doc-sheet .letter-to { margin-bottom:1.2em; }
.doc-sheet table.letter-thru { border-collapse:collapse; margin-bottom:1.2em; }
.doc-sheet table.letter-thru td { vertical-align:top; padding:0 0 .5em; }
.doc-sheet table.letter-thru td.k { width:3.5em; }
.doc-sheet .letter-par { text-align:justify; text-indent:3em; }
.doc-sheet .letter-closing { margin:1.4em 0 0; }
