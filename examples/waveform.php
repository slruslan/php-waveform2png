<?php
include "../library.php";

// Create new instance of library
$graph = new Waveform2Png();

// Set all required settings
$graph->setHeight(200);
$graph->setWidth(1200);
$graph->setBackground('');
$graph->setForeground('#35cc32');
// Defines level of detalization.
// The bigger value means smaller detalization.
$graph->setDetail(20);
// Sets type of graph (available values: waveform, bars)
$graph->setType('waveform');

// Load file and process
$graph->loadFile("test.mp3");
$graph->process();

// Save and output image
$graph->saveImage('waveform_example.png');
$graph->outputImage();