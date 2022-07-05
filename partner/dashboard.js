/* globals Chart:false, feather:false */

(function () {
  'use strict'

  feather.replace({ 'aria-hidden': 'true' })

  
})();

let graphs = document.querySelectorAll('.card');

let renders = [];
graphs.forEach(ele => {
  renders[ele.dataset.sequence] = (sequence) => {
    let input = document.getElementById(`type_${sequence}`)
    let type = "";
    if(input){
      type = input.value;
    }
    $.get( "graphs.php", { graph: sequence, type })
    .done(function( data ) {      
      let json = JSON.parse(data);
      console.log(sequence);
      console.log(json);
      var ctx = document.getElementById(`chart${sequence}`).getContext('2d');
      var myChart = new Chart(ctx, {
        type: json.type,
        data: json.data,
        options: json.options
      });
    });
  }
});

renders.forEach((e, key) => {
  e(key);
});

let items = document.querySelectorAll('.chart-container .card');

function handleDragStart(e) {
  this.style.opacity = '0.4';
  dragSrcEl = this;

  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/html', this.innerHTML);
  e.dataTransfer.setData('sequence', this.dataset.sequence);
}

function handleDragEnd(e) {
  this.style.opacity = '1';

  items.forEach(function (item) {
    item.classList.remove('over');
  });
}

function handleDragOver(e) {
  e.preventDefault();
  return false;
}

function handleDragEnter(e) {
  this.classList.add('over');
}

function handleDragLeave(e) {
  this.classList.remove('over');
}
function handleDrop(e) {
  e.stopPropagation(); // stops the browser from redirecting.

  if (dragSrcEl !== this) {
    dragSrcEl.innerHTML = this.innerHTML;

    let renderChratSequenceCurrent = this.dataset.sequence;
    let renderChratSequenceDragged = e.dataTransfer.getData('sequence');
    this.dataset.sequence = renderChratSequenceDragged;
    dragSrcEl.dataset.sequence = renderChratSequenceCurrent;

    this.innerHTML = e.dataTransfer.getData('text/html');

    let graphs = document.querySelectorAll('.card');

    let graph_sequence = [];
    graphs.forEach(e => {
      graph_sequence.push(e.dataset.sequence);
    });
    $.post( "arrange_graph.php", { graph_sequence })
    .done(function( data ) {
      renders[renderChratSequenceDragged](renderChratSequenceDragged);
      renders[renderChratSequenceCurrent](renderChratSequenceCurrent);
    });
  }

  return false;
}
function changeGraphType(e){
  renders[e.currentTarget.dataset.sequence](e.currentTarget.dataset.sequence);
}
items.forEach(function (item) {
  let input = document.getElementById(`type_${item.dataset.sequence}`);
  if(input){
    input.addEventListener('change', changeGraphType);
  }
    item.addEventListener('dragstart', handleDragStart);
    item.addEventListener('dragover', handleDragOver);
    item.addEventListener('dragenter', handleDragEnter);
    item.addEventListener('dragleave', handleDragLeave);
    item.addEventListener('dragend', handleDragEnd);
    item.addEventListener('drop', handleDrop);
});

// setInterval(() => {
//   renders.forEach((e, key) => {
//     e(key);
//   });
// }, 10000)

// renderChart1();
// renderChart2();