<?php
include "../library.php";

// Create new instance of library
$graph = new Waveform2Png();

// Set all required settings
$graph->setHeight(200);
$graph->setWidth(1200);
$graph->setBackground('');
$graph->setForeground('#d6d6d6');
// Defines level of detalization.
// The bigger value means smaller detalization.
$graph->setDetail(100);
// Sets type of graph (available values: waveform, bars)
$graph->setType('bars');

// Set different colors for different parts of graph
$graph->addColor(5, 10, '#ff0303');
$graph->addColor(20, 30, '#a9ff03');
$graph->addColor(40, 50, '#03fcff');
$graph->addColor(60, 70, '#de03ff');
$graph->addColor(80, 100, '#ff0374');
$graph->addColor(120, 130, '#fffc03');

// Load file and process
$graph->loadFile("test.mp3");
$graph->process();

// Save and output image
$graph->saveImage('multicolor_example.png');
$graph->outputImage();