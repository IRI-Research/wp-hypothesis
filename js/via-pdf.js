var anchors = document.getElementsByTagName('a');
var hypRe = new RegExp( HypothesisPDF.uploadsBase + '.+\.pdf', 'i' );
for ( i=0; i<anchors.length; i++ ) {
   var href = anchors[i].href;
   if ( href.match(hypRe) )
       anchors[i].href = via_base_url + '/' + anchors[i].href;
  }
