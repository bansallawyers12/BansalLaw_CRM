/* var ctx = document.getElementById("myChart").getContext('2d');
var myChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
    datasets: [{
      label: 'Statistics',
      data: [460, 458, 330, 502, 430, 610, 488],
      borderWidth: 2,
      backgroundColor: 'rgba(145,141,197,.8)',
      borderWidth: 0,
      borderColor: 'transparent',
      pointBorderWidth: 0,
      pointRadius: 3.5,
      pointBackgroundColor: 'transparent',
      pointHoverBackgroundColor: 'rgba(63,82,227,.8)',
    }, {
      label: 'Statistics',
      data: [390, 600, 390, 280, 600, 430, 638],
      borderWidth: 2,
      backgroundColor: 'rgba(58,184,214,.7)',
      borderWidth: 0,
      borderColor: 'transparent',
      pointBorderWidth: 0,
      pointRadius: 3.5,
      pointBackgroundColor: 'transparent',
      pointHoverBackgroundColor: 'rgba(254,86,83,.8)',
    }]
  },
  options: {
    legend: {
      display: false
    },
    scales: {
      yAxes: [{
        gridLines: {
          drawBorder: false,
          color: '#f2f2f2',
        },
        ticks: {
          beginAtZero: true,
          stepSize: 200,
          fontColor: "#9aa0ac", // Font Color
          callback: function (value, index, values) {
            return value;
          }
        }
      }],
      xAxes: [{
        gridLines: {
          display: false,
          tickMarkLength: 15,
        },
        ticks: {
          fontColor: "#9aa0ac", // Font Color
        }
      }]
    },
  }
}); */

var ctx = document.getElementById("application_workflow").getContext('2d');
var myChart = new Chart(ctx, {
	type: 'pie',
	data: {
		datasets: [{
			data:data,
			backgroundColor: [
				
				'#63ed7a',
				'#ffa426',
				//'#fc544b',
				'#6777ef',
			],
			label: 'Dataset 1'
		}], 
		labels: [
		    	'Total Leads',
			'Coverted To Clients',
			'In Progress', 
		
		],
	},
	options: {
		responsive: true,
		legend: {
			position: 'bottom',
		},
	}
});    

var ctx = document.getElementById("client_application").getContext('2d');
var myChart = new Chart(ctx, {
	type: 'doughnut',
	data: {
		datasets: [{
			data: dataapplication,
			backgroundColor: [
				'#ffa426',
				'#63ed7a',
				
				'#fc544b',
			
			],
			label: 'Dataset 1'
		}],
		labels: [
		    'In Progress',
			'Completed',
			
			'Discontinued',
			
		],
	},
	options: {
		responsive: true,
		legend: {
			position: 'bottom',
		},
	}
});

var ctx = document.getElementById("partner_application").getContext('2d');
var myChart = new Chart(ctx, {
	type: 'bar',
	data: {
		labels: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
		datasets: [{
			label: 'Statistics',
			data: [460, 458, 330, 502, 430, 610, 488],
			borderWidth: 2,
			backgroundColor: '#6777ef',
			borderColor: '#6777ef',
			borderWidth: 2.5,
			pointBackgroundColor: '#ffffff',
			pointRadius: 4
		}]
	},
	options: {
		legend: {
			display: false
		},
		scales: {
			yAxes: [{
				gridLines: {
					drawBorder: false,
					color: '#f2f2f2',
				},
				ticks: {
					beginAtZero: true,
					stepSize: 150,
					fontColor: "#9aa0ac", // Font Color
				}
			}],
			xAxes: [{
				ticks: {
					display: false
				},
				gridLines: {
					display: false
				}
			}]
		},
	}
});