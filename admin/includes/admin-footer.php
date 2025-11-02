            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js for Dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Admin JS -->
    <script>
        // Dashboard Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Sales Chart
            const salesCtx = document.getElementById('salesChart');
            if(salesCtx) {
                new Chart(salesCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Sales',
                            data: [12000, 19000, 15000, 25000, 22000, 30000],
                            borderColor: '#d4af37',
                            backgroundColor: 'rgba(212, 175, 55, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        }
                    }
                });
            }

            // Payment Methods Chart
            const paymentCtx = document.getElementById('paymentChart');
            if(paymentCtx) {
                new Chart(paymentCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['JazzCash Mobile', 'JazzCash Card', 'Cash on Delivery'],
                        datasets: [{
                            data: [45, 35, 20],
                            backgroundColor: [
                                '#d4af37',
                                '#2c3e50',
                                '#27ae60'
                            ]
                        }]
                    }
                });
            }
        });

        // Confirm delete actions
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }

        // Toggle product status
        function toggleProductStatus(productId, currentStatus) {
            fetch('ajax/toggle-product-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    status: currentStatus === 'active' ? 'inactive' : 'active'
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                }
            });
        }
    </script>
</body>
</html>