<?php
include 'dbconnect.php';
//Set unlimited script execution time
set_time_limit(0);
//Supress warnings
error_reporting(E_ERROR | E_PARSE);
?>


<html>
    <head>
        <meta charset="UTF-8">
        <title>Research Web Portal</title>
        <link rel="stylesheet" href="css/style.css" type="text/css" />
    </head>
    <body>
        <!-- Progress bar holder -->
        <div id="progress" style="width:500px;border:1px solid #000;"></div>
        <!-- Progress information -->
        <div id="information" style="width"></div>
        <div>
            <?php
            if (!empty($_GET['pcode'])) {
                $prcode = filter_input(INPUT_GET, 'pcode');
                $conn = dbconnect();
                $sql = "SELECT * FROM investigator i where i.FK_Award in (SELECT a.FK_rootTag FROM programelement pe ,award a WHERE pe.FK_award = a.FK_rootTag and pe.code = '" . $prcode . "')";
                $result = $conn->query($sql);
                $rowcount = mysqli_num_rows($result);
                if ($rowcount > 0) {
                    while ($row = $result->fetch_assoc()) {

                        if ($row["RoleCode"] == "Principal Investigator") {
//                          Build author array from query result
                            $auth[] = $row;
                        } else {
//                          Build co-author array from query result  
                            $coauth[] = $row;
                        }
                    }

                    echo "Author count : " . count($auth);
                    echo "<br/>Co - Author count : " . count($coauth);
                }
                $conn->close();
            }
            ?>
        </div>
        <div>

            <?php
//          Run loop for j = number of authors
            $mcount=0;
            for ($j = 0; $j < count($auth); $j++) {
//              Calculate progress bar percentage
                $percent = intval($j / count($auth) * 100) . "%";
//              Update progress bar
                echo '<script language="javascript">
                    document.getElementById("progress").innerHTML="<div style=\"width:' . $percent . ';background-color:#009900;\">&nbsp;</div>";
                    document.getElementById("information").innerHTML="' . $j . ' author(s) processed. '.$mcount.' author(s) matched.";
                    </script>';
//              This is for the buffer achieve the minimum size in order to flush data
                echo str_repeat(' ', 1024 * 64);
//              Send output to browser immediately
                flush();
//              Initialize variables
                $arow = $auth[$j];
                $match = 'No Match';
                
                $coautharr = [];
//              Build co-author array for current author
                foreach ($coauth as $coarray) {
                    if ($coarray["FK_Award"] == $arow["FK_Award"]) {
                        $coautharr[] = $coarray;
                    }
                }
//              Build Scopus Author Search query
                $auth_url = 'https://api.elsevier.com/content/search/author?query=authlast(' . urlencode($arow["LastName"]) . ')%20and%20authfirst(' . urlencode($arow["FirstName"]) . ')&insttoken=' . $insttoken . '&apiKey=' . $apiKey . '&httpAccept=application/json';
                $opts = array('http' => array('header' => "User-Agent:MyAgent/1.0\r\n"));
                $context = stream_context_create($opts);
//              Run query and get result in $authorres
                $authorres = file_get_contents($auth_url, false, $context);
//              Decode JSON received from API
                $author_json = json_decode($authorres, true);
//                echo 'Count author_json :' . count($author_json['search-results']['entry']);

                if (!empty($author_json)) {

                    foreach ($author_json['search-results']['entry'] as $author) {
//                            echo '<br/>' . $author['preferred-name']['given-name'] . ' ' . $author['preferred-name']['surname'] . '<br/>Document Count :' . $author['document-count'] . '<br/>';
//                      Extract author id for current author
                        list($a, $b, $auid) = explode('-', $author['eid']);
//                      Build Scopus co-author search query
                        $coauthurl = 'https://api.elsevier.com/content/search/author?co-author=' . urlencode($auid) . '&insttoken=' . $insttoken . '&apiKey=' . $apiKey . '&httpAccept=application/json';
                        $opts = array('http' => array('header' => "User-Agent:MyAgent/1.0\r\n"));
                        $context = stream_context_create($opts);
                        $coauthorres = file_get_contents($coauthurl, false, $context);
                        $coauthor_json = json_decode($coauthorres, true);
//                      Hard code for single result
                        if (count($author_json['search-results']['entry']) == 1) {
                            $match = $auid . ' - Single Result Match';
                        }
                        if (!empty($coautharr)) {
                            for ($i = 0; $i < count($coautharr); $i++) {
//                              Concat NSF co-author FirstName and LastName for levenshtein
                                $cofullnsf = $coautharr[$i]['FirstName'] . " " . $coautharr[$i]['LastName'];

                                if (!empty($coauthor_json)) {
                                    foreach ($coauthor_json['search-results']['entry'] as $coauthor) {
//                                      Concat Scopus co-author FirstName and LastName for lev
                                        $cofull = $coauthor['preferred-name']['given-name'] . " " . $coauthor['preferred-name']['surname'];
//                                      Fetch similarity between NSF co-author and Scopus co-author
                                        $similarity = lev(strtolower($cofullnsf), strtolower($cofull));
//                                      Similarity threshold = 0.4 (Good results) 
                                        if ($similarity < 0.4) {
                                            $match = $auid . ' - Co-Author Match';
                                        }
//                                        echo '<br/>NSF co full : ' . $cofullnsf . '<br/>Scopus co full : ' . $cofull . '<br/>Similarity : ' . $similarity;
                                    }
                                }
                            }
                        }
//                      Compare affiliation cities if still no match found
                        if ($match == 'No Match') {
//                            echo '-- In No Match if --';
                            $awa = $arow["FK_Award"];
                            $conn = dbconnect();
                            $citysql = 'SELECT * FROM institution WHERE FK_Award = ' . $awa;
                            $cresult = $conn->query($citysql);
                            $crow = $cresult->fetch_assoc();
//                            echo $crow["CityName"];
//                            echo $author['affiliation-current']['affiliation-city'];
                            $cname = $crow["CityName"];
                            $cname_scopus = $author['affiliation-current']['affiliation-city'];
                            if (!empty($cname) && !empty($cname_scopus)) {
                                $citysim = lev(strtolower($cname), strtolower($cname_scopus));
                                if ($citysim < 0.4) {
                                    $match = $auid . ' - Affiliation City Match';
                                }
                            }
                            mysqli_close($conn);
                        }
                    }
                }
//              Print results of matching
                if($match != 'No Match'){
                    $mcount++;
                }
                echo '<br/>NSF Author Name : ' . $arow["FirstName"] . " " . $arow["LastName"];
                echo '<br/>Scopus Author ID : ' . $match . '<br/>';
            }
//          Update progress bar when completed
            $percent = "100%";
            echo '<script language="javascript">document.getElementById("progress").innerHTML="<div style=\"width:' . $percent . ';background-color:#009900;\">&nbsp;</div>";'
            . 'document.getElementById("information").innerHTML="' . $j . ' author(s) processed. Process completed. '.$mcount.' author(s) matched."</script>';
            ?>

        </div>
    </body>
</html>