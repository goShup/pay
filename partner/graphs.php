<?php

include("b.php");
echo $partner_graph->GenerateView($REQ["type"],$REQ["data"],$partner_graph->getDataSource($REQ["data"])["length"],$REQ["source"]);
