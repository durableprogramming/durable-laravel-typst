#set page(width: 10cm, height: auto)

= Broken Document

This document has intentional syntax errors for testing.

#invalid-function()

== Section with Missing Closing Bracket

#table(
  columns: 2,
  [*Name*], [*Value*],
  [Test], [123
  // Missing closing bracket above

#unknown-command {
  content