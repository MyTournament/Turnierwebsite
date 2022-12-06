         
<script language="JavaScript">
/******************************************
* Snow Effect Script- By Altan d.o.o. (http://www.altan.hr/snow/index.html)
* Visit Dynamic Drive DHTML code library (http://www.dynamicdrive.com/) for full source code
* Last updated Nov 9th, 05' by DD. This notice must stay intact for use
******************************************/
    //Configure below to change URL path to the snow image
    var snowsrc="images/icon/cookie.png"
    // Configure below to change number of snow to render
    var no = 20;
    // Configure whether snow should disappear after x seconds (0=never):
    var hidesnowtime = 0;
    // Configure how much snow should drop down before fading ("windowheight" or "pageheight")
    var snowdistance = "pageheight";
    //0 before start, after that 1
    var startbool=0;
    var size=50;
    function start(){
    startbool=1;
    cookie_init();
    }
///////////Stop Config//////////////////////////////////
    
    var ie4up = (document.all) ? 1 : 0;
    var ns6up = (document.getElementById&&!document.all) ? 1 : 0;
        function iecompattest(){
        return (document.compatMode && document.compatMode!="BackCompat")? document.documentElement : document.body
        }

    var dx, xp, yp;    // coordinate and position variables
    var am, stx, sty;  // amplitude and step variables
    var i, doc_width = 800, doc_height = 600;

    if (ns6up) {
    doc_width = self.innerWidth;
    doc_height = self.innerHeight;
    } else if (ie4up) {
    doc_width = iecompattest().clientWidth;
    doc_height = iecompattest().clientHeight;
    }
    var body = document.body, html = document.documentElement;
    doc_height = Math.max(   document.body.scrollHeight, document.documentElement.scrollHeight,
    document.body.offsetHeight, document.documentElement.offsetHeight,
    document.body.clientHeight, document.documentElement.clientHeight);

    dx = new Array();
    xp = new Array();
    yp = new Array();
    am = new Array();
    stx = new Array();
    sty = new Array();

    for (i = 0; i < no; ++ i) {
        
    dx[i] = 0;                        // set coordinate variables
    xp[i] = (Math.random()*(doc_width-200))+100;  // set position variables
    yp[i] = Math.random()*doc_height;
    am[i] = Math.random()*20;         // set amplitude variables
    stx[i] = 0.02 + Math.random()/10; // set step variables
    sty[i] = 0.7 + Math.random();     // set step variables
                if (ie4up||ns6up) {
        if (i == 0) {
        document.write("<div id=\"dot"+ i +"\" style=\"POSITION: absolute; Z-INDEX: "+ i +"; VISIBILITY: hidden; TOP: 15px; LEFT: 15px;\"><a href=\"http://dynamicdrive.com\"><img width= "+size+"px' height='"+size+"px' src='"+snowsrc+"' border=\"0\"><\/a><\/div>");
        } else {
        document.write("<div id=\"dot"+ i +"\" style=\"POSITION: absolute; Z-INDEX: "+ i +"; VISIBILITY: hidden; TOP: 15px; LEFT: 15px;\"><img width= '"+size+"px' height='"+size+"px' src='"+snowsrc+"' border=\"0\"><\/div>");
        }
    }
    }
    function cookie_init(){
        for (i=0; i<no; i++) document.getElementById("dot"+i).style.visibility="visible"
    }
    

    function snowIE_NS6() {  // IE and NS6 main animation function
    doc_width = ns6up?window.innerWidth-10 : iecompattest().clientWidth-10;
                doc_height=(window.innerHeight && snowdistance=="windowheight")? window.innerHeight : (ie4up && snowdistance=="windowheight")?  iecompattest().clientHeight : (ie4up && !window.opera && snowdistance=="pageheight")? iecompattest().scrollHeight : iecompattest().offsetHeight;
    for (i = 0; i < no; ++ i) {  // iterate for every dot
        
        if(startbool==1){
            yp[i] += sty[i];
        if (yp[i] > doc_height-50) {
        xp[i] = Math.random()*(doc_width-am[i]-30);
        yp[i] = 60;
        stx[i] = 0.02 + Math.random()/10;
        sty[i] = 0.7 + Math.random();
        }
        dx[i] += stx[i];
        document.getElementById("dot"+i).style.top=yp[i]+"px";
        document.getElementById("dot"+i).style.left=xp[i] + am[i]*Math.sin(dx[i])+"px";
        }
    }
    snowtimer=setTimeout("snowIE_NS6()", 10);
    }

        function hidesnow(){
                if (window.snowtimer) clearTimeout(snowtimer)
                for (i=0; i<no; i++) document.getElementById("dot"+i).style.visibility="hidden"
        }


if (ie4up||ns6up){
    snowIE_NS6();
                if (hidesnowtime>0)
                setTimeout("hidesnow()", hidesnowtime*1000)
                }
    
//*****************************************************************************
//********************END OF THE SNOW SCRIPT***********************************
//*****************************************************************************
</script>