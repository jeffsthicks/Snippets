<?php
$tagName = $_GET['tag'];
header("Content-Disposition: attachment; filename=\"$tagName.tex\"");

// Retrieve the URL variables (using PHP).

$fileLocation= "./tags/$tagName.tex";
$texTagName=preg_replace("/\_/","\_",$tagName);
$sectionDepth=-1;
function texReader($fileLocation) {
    global $sectionDepth;
    $bodyText="";

    $f = fopen($fileLocation, "r");
       {# Build up the metadata for this entry
        while (($line = fgets($f))[0] == "%"){
            if (preg_match('/name:"(.*)"/',$line,$matches)==1){
                $name=$matches[1];
                $name = preg_replace("/\\$([^\\$]*)\\$/","\($1\)",$name);}
            if (preg_match('/type:"(.*)"/',$line,$matches)==1){
                $type=$matches[1];
            }
            if (preg_match('/label:"(.*)"/',$line,$matches)==1){
                $label=$matches[1];
            }
            if (preg_match('/caption:"(.*)"/',$line,$matches)==1){
                $caption=$matches[1];
                $caption = preg_replace("/\\$([^\\$]*)\\$/","\($1\)",$caption);
            }
            if (preg_match('/source:"(.*)"/',$line,$matches)==1){
                $sourceTag=$matches[1];
            }
            if (preg_match('/sourceDetail:"(.*)"/',$line,$matches)==1){
                $sourceDetail=$matches[1];
            }
        }
        if (isset($name)==False)
            {$name="NONAME";}
        if (isset($type)==False)
            {$type="NOTYPE";}
        if (isset($label)==False)
            {$label="NOLABEL";}
        if (isset($caption)==False)
            {$caption="NOLABEL";}
        if (isset($sourceTag)==False)
            {$source="";}
        else{
            if (isset($sourceDetail)==False){$source="\cite{{$sourceTag}}";}
            else{$source="\cite[$sourceDetail]{{$sourceTag}}";}
        }
        
    }


    if (in_array($type,["article","exposition","construction"])){
        $sectionDepth++;
        if($sectionDepth>0){
            $subString="";
            for ($i=0;$i<$sectionDepth-1;$i++){$subString=$subString."sub";}
            $envOpen="\\{$subString}section{{$name}}\n\\label{{$label}}\n";
            $envClose="";}
        else{$envOpen="\\title{{$name}}\n \\maketitle\n \\thispagestyle{firstpage}";
            $envClose="";}
        
    }
    else if (in_array($type,["theorem","definition","proposition","lemma","example","exercise","proof"])){
        $envOpen="\\begin{{$type}}$source\n\\label{{$label}}\n";
        $envClose="\\end{{$type}}\n";
    }
    else if (in_array($type,["figure"])){
        $envOpen="\\begin{figure}\n \\label{{$label}}\n \centering\n ";
        $envClose="\\caption{{$caption}}\n \\end{figure}";
    }
    else if (in_array($type,["diagram"])){
        $envOpen="\\[ ";
        $envClose="\\]";
    }
    else{
        $envOpen="";
        $envClose="";
    }
    while (($line = fgets($f)) !== false)
    {
        if (preg_match("/\\\\input{figures\/([^\}]*)\}/",$line,$matches)==1){
            #$inputLocation="./tags/figures/$matches[1]";
            #$bodyText=$bodyText.texReader($inputLocation);
        }   elseif (preg_match("/\\\\input{([^\}]*)\}/",$line,$matches)==1){
            $inputLocation="./tags/$matches[1].tex";
            $bodyText=$bodyText.texReader($inputLocation);
        }
        elseif ($line[0]=="%"){
            }
        else{
            $bodyText=$bodyText.$line;
        }
    }
    fclose($f);

    
    $bodyText= $envOpen.$bodyText.$envClose;

    if (in_array($type,["article","exposition","construction"])){$sectionDepth--;}
    return $bodyText;
    
}
    
echo"\\documentclass[11 pt]{article}\n";
echo(texReader("./code/preambles/preamble.tex"));
echo("\\fancypagestyle{firstpage}{%\n
      \\fancyhf{}
      \\renewcommand\headrulewidth{0pt}
      \\fancyfoot[R]{Original text at \\texttt{ \\href{http://jeffhicks.net/snippets/index.php?tag=$tagName}{snippets/$texTagName}}}
    }
    "); 
echo(texReader("./code/preambles/mathpreamble.tex"));
echo("\\begin{filecontents}{references.bib}\n");
echo(texReader("./code/preambles/references.bib"));
echo("\\end{filecontents}\n");
echo("\addbibresource{references.bib}");
echo("\\begin{document}\n\n");
echo(texReader($fileLocation));
echo("\\printbibliography\n \\end{document}");

?>