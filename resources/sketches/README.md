# Anatomical Sketches

The package includes 15 source SVGs with their original `<title>` and `<desc>`
metadata. The metadata identifies the
artwork as extracted with permission from INTERPOL DVI Form 2023, physical PDF
page 14, Appendix 2 (835 Body Sketch).

The catalog in `../js/sketches.js` defines 15 views: front/back body; front/back,
left/right profiles and top of the head; left/right palmar/dorsal hands; and
left/right dorsal/plantar feet. Anatomical left and right refer to the subject.
The drawings do not define semantic region boundaries.

See the [body-region reference](../../docs/body-regions.md) for illustrated
selection maps and standard labels, and the [workspace guide](../../docs/workspace.md)
for marker placement and diagram controls.

`../region-surfaces.json` stores normalized outlines from the white-filled paths
in all 15 drawings, plus enlarged nipple landmarks derived from the front body's
two circles. Region suggestions use these surfaces to include anatomical edges
and exclude spaces between limbs and digits. Semantic feature zones remain in
`../js/observationRegions.js`, with broader labels covering their remaining gaps.

Regenerate with `php scripts/generate-region-surfaces.php` after changing artwork.
The generator includes ancestor transforms, adaptively flattens cubic curves to
a 0.0001 normalized tolerance, and simplifies with a further 0.0001 tolerance.
Region lookup allows 0.0003 at the flattened edges; placement still checks the
original SVG fill. The generator rejects unsupported transforms, path commands,
and compound fills for manual review. Source SHA-256 hashes are checked by the
frontend tests. Consumers use the committed data and do not need to run this
generator. No database migration is required.

Builds may change asset URLs. Keep the catalog's stable IDs (such as
`body-front` and `hand-left-palmar`) when referring to an observation's location.
Keep the source SVGs, their view boxes, and their attribution together when
distributing or reusing the drawings.
