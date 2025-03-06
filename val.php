                    <button type="submit" name="login" class="submit-btn">Log In</button>
                    <div class="message" id="loginMessage"></div>
                </form>
            </div>

            <div id="signup-form" class="auth-form">
                <h2>Sign Up</h2>
                <form id="signupForm" action="auth.php" method="POST">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    <button type="submit" name="signup" class="submit-btn">Sign Up</button>
                    <div class="message" id="signupMessage"></div>
                </form>
            </div>

            <div id="reset-form" class="auth-form">
                <h2>Reset Password</h2>
                <form id="resetRequestForm" action="auth.php" method="POST">
                    <input type="email" name="email" placeholder="Email" required>
                    <button type="submit" name="reset_request" class="submit-btn">Send Reset Link</button>
                    <div class="message" id="resetMessage"></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchView(view) {
            const gridView = document.getElementById('match-grid');
            const tableView = document.getElementById('match-table');
            const standingsView = document.getElementById('standings-table');
            const scorersView = document.getElementById('top-scorers-table');
            const gridBtn = document.getElementById('grid-view-btn');
            const tableBtn = document.getElementById('table-view-btn');
            const standingsBtn = document.getElementById('standings-view-btn');
            const scorersBtn = document.getElementById('scorers-view-btn');

            gridView.style.display = 'none';
            tableView.style.display = 'none';
            standingsView.style.display = 'none';
            scorersView.style.display = 'none';
            gridBtn.classList.remove('active');
            tableBtn.classList.remove('active');
            standingsBtn.classList.remove('active');
            scorersBtn.classList.remove('active');

            if (view === 'grid') {
                gridView.style.display = 'grid';
                gridBtn.classList.add('active');
            } else if (view === 'table') {
                tableView.style.display = 'block';
                tableBtn.classList.add('active');
            } else if (view === 'standings') {
                standingsView.style.display = 'block';
                standingsBtn.classList.add('active');
            } else if (view === 'scorers') {
                scorersView.style.display = 'block';
                scorersBtn.classList.add('active');
            }
            
            localStorage.setItem('matchView', view);
        }

        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown) {
                dropdown.classList.toggle('active');
            }
        }

        document.addEventListener('click', function(e) {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu && !userMenu.contains(e.target)) {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown) {
                    dropdown.classList.remove('active');
                }
            }
        });

        function openModal() {
            const modal = document.getElementById("auth-modal");
            if (modal) {
                modal.classList.add("active");
            }
        }

        function closeModal() {
            const modal = document.getElementById("auth-modal");
            if (modal) {
                modal.classList.remove("active");
                document.querySelectorAll('.auth-form input').forEach(input => input.value = '');
                document.querySelectorAll('.message').forEach(msg => msg.textContent = '');
            }
        }

        function showForm(formType) {
            const forms = document.querySelectorAll('.auth-form');
            forms.forEach(form => form.classList.remove('active'));
            const targetForm = document.getElementById(formType + "-form");
            if (targetForm) {
                targetForm.classList.add("active");
            }
            const tabs = document.querySelectorAll('.tab-buttons button');
            tabs.forEach(tab => tab.classList.remove('active'));
            const targetTab = document.getElementById(formType + "-tab");
            if (targetTab) {
                targetTab.classList.add("active");
            }
            document.querySelectorAll('.message').forEach(msg => msg.textContent = '');
        }

        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('login', true);
            submitForm(formData, 'loginMessage', () => window.location.reload());
        });

        document.getElementById('signupForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('signup', true);
            submitForm(formData, 'signupMessage', () => window.location.reload());
        });

        document.getElementById('resetRequestForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('reset_request', true);
            submitForm(formData, 'resetMessage', () => closeModal());
        });

        function submitForm(formData, messageId, callback) {
            const messageDiv = document.getElementById(messageId);
            if (!messageDiv) {
                console.error(`Message div with ID '${messageId}' not found`);
                return;
            }
            messageDiv.textContent = 'Processing...';

            fetch('auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP error! Status: ${response.status} (${response.statusText}) - ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                messageDiv.textContent = data.message || 'No message returned';
                if (data.success) {
                    formData.forEach((value, key) => {
                        if (key !== 'login' && key !== 'signup' && key !== 'reset_request') {
                            const input = document.querySelector(`[name="${key}"]`);
                            if (input) input.value = '';
                        }
                    });
                    if (callback) {
                        setTimeout(() => callback(data), 2000);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.textContent = error.message;
            });
        }

        function shareScorersAsImage() {
            const tableElement = document.querySelector('#top-scorers-table table');
            const shareBtn = document.getElementById('share-scorers-btn');
            
            shareBtn.disabled = true;
            shareBtn.innerHTML = '<span class="share-icon">⏳</span> Processing...';

            html2canvas(tableElement, {
                backgroundColor: getComputedStyle(document.body).getPropertyValue('--card-bg'),
                scale: 2
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const fileName = `CPS_TopScorers_${new Date().toISOString().replace(/[-:T]/g, '').split('.')[0]}_${Math.random().toString(36).substring(2, 6)}.png`;

                if (navigator.share && navigator.canShare && navigator.canShare({ files: [] })) {
                    canvas.toBlob(blob => {
                        const file = new File([blob], fileName, { type: 'image/png' });
                        navigator.share({
                            title: `${ '<?php echo $selectedComp; ?>' } Top Scorers`,
                            text: 'Check out the top scorers!',
                            files: [file]
                        }).then(() => console.log('Top Scorers shared successfully'))
                          .catch(err => {
                              console.error('Share failed:', err);
                              fallbackDownload(imgData, fileName);
                          });
                    });
                } else {
                    fallbackDownload(imgData, fileName);
                }
            }).catch(error => {
                console.error('Error generating image:', error);
                alert('Failed to generate top scorers image. Please try again.');
            }).finally(() => {
                shareBtn.disabled = false;
                shareBtn.innerHTML = '<span class="share-icon">📤</span> Share';
            });
        }

        document.getElementById('share-scorers-btn').addEventListener('click', shareScorersAsImage);

        document.getElementById('scorers-view-btn').addEventListener('click', function() {
            switchView('scorers');
            setTimeout(() => {
                const table = document.getElementById('top-scorers-table');
                if (table.style.display !== 'none') {
                    table.scrollIntoView({ behavior: 'smooth' });
                }
            }, 100);
        });

        function shareStandingsAsImage() {
            const tableElement = document.querySelector('#standings-table table');
            const shareBtn = document.getElementById('share-standings-btn');
            
            shareBtn.disabled = true;
            shareBtn.innerHTML = '<span class="share-icon">⏳</span> Processing...';

            html2canvas(tableElement, {
                backgroundColor: getComputedStyle(document.body).getPropertyValue('--card-bg'),
                scale: 2
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const fileName = `CPS_Standings_${new Date().toISOString().replace(/[-:T]/g, '').split('.')[0]}_${Math.random().toString(36).substring(2, 6)}.png`;

                if (navigator.share && navigator.canShare && navigator.canShare({ files: [] })) {
                    canvas.toBlob(blob => {
                        const file = new File([blob], fileName, { type: 'image/png' });
                        navigator.share({
                            title: `${ '<?php echo $selectedComp; ?>' } Standings`,
                            text: 'Check out the latest standings!',
                            files: [file]
                        }).then(() => console.log('Standings shared successfully'))
                          .catch(err => {
                              console.error('Share failed:', err);
                              fallbackDownload(imgData, fileName);
                          });
                    });
                } else {
                    fallbackDownload(imgData, fileName);
                }
            }).catch(error => {
                console.error('Error generating image:', error);
                alert('Failed to generate standings image. Please try again.');
            }).finally(() => {
                shareBtn.disabled = false;
                shareBtn.innerHTML = '<span class="share-icon">📤</span> Share';
            });
        }

        document.getElementById('share-standings-btn').addEventListener('click', shareStandingsAsImage);

        document.getElementById('standings-view-btn').addEventListener('click', function() {
            switchView('standings');
            setTimeout(() => {
                const table = document.getElementById('standings-table');
                if (table.style.display !== 'none') {
                    table.scrollIntoView({ behavior: 'smooth' });
                }
            }, 100);
        });

        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', newTheme);
            document.cookie = `theme=${newTheme};path=/;max-age=31536000`;
            document.querySelectorAll('.match-info').forEach(el => {
                el.classList.toggle('dark', newTheme === 'dark');
            });
            const themeIcon = document.querySelector('.theme-icon');
            themeIcon.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        }

        function toggleHistory(button) {
            const historyDiv = button.nextElementSibling;
            const isHidden = historyDiv.style.display === 'none';
            historyDiv.style.display = isHidden ? 'block' : 'none';
            button.textContent = isHidden ? '👁️ Hide History' : '👁️ View History';
        }

        function updateUrl(comp, filter) {
            let url = `?competition=${comp}&filter=${filter}`;
            if (filter === 'custom') {
                const start = document.querySelector('input[name="start"]').value;
                const end = document.querySelector('input[name="end"]').value;
                if (start && end) url += `&start=${start}&end=${end}`;
            }
            const searchTeam = document.querySelector('.search-input').value.trim();
            if (searchTeam) url += `&team=${encodeURIComponent(searchTeam)}`;
            window.location.href = url;
        }

        function selectFilter(filter) {
            if (filter !== 'custom') {
                updateUrl('<?php echo $selectedComp; ?>', filter);
            } else {
                document.querySelector('.custom-date-range').classList.add('active');
            }
        }

        document.querySelector('.filter-dropdown-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.querySelector('.filter-dropdown');
            dropdown.classList.toggle('active');
            const customRange = document.querySelector('.custom-date-range');
            if ('<?php echo $filter; ?>' === 'custom') {
                customRange.classList.add('active');
            } else {
                customRange.classList.remove('active');
            }
        });

        document.addEventListener('click', function(e) {
            const container = document.querySelector('.filter-container');
            if (!container.contains(e.target)) {
                document.querySelector('.filter-dropdown').classList.remove('active');
            }
        });

        function fetchTeamData(teamId, index, isHome, attempt = 0, maxAttempts = 10) {
            const delay = Math.min(Math.pow(2, attempt) * 1000, 10000);
            const formElement = document.getElementById(`form-${isHome ? 'home' : 'away'}-${index}`);
            const tableFormElement = document.getElementById(`table-form-${isHome ? 'home' : 'away'}-${index}`);
            const historyElement = document.getElementById(`history-${index}`);
            const predictionElement = document.getElementById(`prediction-${index}`);
            const progressBar = predictionElement.querySelector('.progress-fill') || document.createElement('div');

            if (!progressBar.classList.contains('progress-fill')) {
                progressBar.classList.add('progress-fill');
                const progressContainer = document.createElement('div');
                progressContainer.classList.add('progress-bar');
                progressContainer.appendChild(progressBar);
                predictionElement.appendChild(progressContainer);
            }

            if (attempt === 5) {
                const retryNotice = document.createElement('div');
                retryNotice.className = 'retry-notice';
                retryNotice.innerHTML = 'Still trying to load data for this team. Please wait...';
                retryNotice.style.cssText = `
                    background-color: #fff3cd; 
                    border: 1px solid #ffeeba; 
                    color: #856404; 
                    padding: 10px; 
                    margin-top: 10px; 
                    border-radius: 5px; 
                    text-align: center;
                    font-size: 0.9em;
                `;
                predictionElement.appendChild(retryNotice);
                setTimeout(() => retryNotice.remove(), 5000);
            }

            fetch(`?action=fetch_team_data&teamId=${teamId}&competition=<?php echo $selectedComp; ?>&force_refresh=true&attempt=${attempt}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTeamUI(data, index, isHome, formElement, tableFormElement, historyElement, predictionElement);
                    progressBar.parentElement.remove();

                    const matchCard = document.querySelector(`.match-card[data-index="${index}"]`);
                    const otherTeamLoaded = isHome ?
                        !document.getElementById(`form-away-${index}`).querySelector('.loading-spinner') :
                        !document.getElementById(`form-home-${index}`).querySelector('.loading-spinner');
                    if (otherTeamLoaded) {
                        fetchPrediction(index, matchCard.dataset.homeId, matchCard.dataset.awayId);
                    }
                } else if (data.retry && attempt < maxAttempts) {
                    progressBar.style.width = `${(attempt + 1) / maxAttempts * 100}%`;
                    setTimeout(() => fetchTeamData(teamId, index, isHome, attempt + 1, maxAttempts), delay);
                } else {
                    console.error(`Max retries reached for team ${teamId}`);
                    formElement.innerHTML = '<p>Error loading data</p>';
                    tableFormElement.innerHTML = '<p>Error</p>';
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                }
            })
            .catch(error => {
                console.error('Error fetching team data:', error);
                if (attempt < maxAttempts) {
                    progressBar.style.width = `${(attempt + 1) / maxAttempts * 100}%`;
                    setTimeout(() => fetchTeamData(teamId, index, isHome, attempt + 1, maxAttempts), delay);
                } else {
                    formElement.innerHTML = '<p>Failed to load data</p>';
                    tableFormElement.innerHTML = '<p>Failed</p>';
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                }
            });
        }

        function updateTeamUI(data, index, isHome, formElement, tableFormElement, historyElement, predictionElement) {
            let formHtml = '';
            const form = data.form.slice(-6).padStart(6, '-');
            const reversedForm = form.split('').reverse().join('');
            for (let i = 0; i < 6; i++) {
                let className = reversedForm[i] === 'W' ? 'win' : (reversedForm[i] === 'D' ? 'draw' : (reversedForm[i] === 'L' ? 'loss' : 'empty'));
                if (i === 5 && reversedForm[i] !== '-' && data.form.trim('-').length > 0) className += ' latest';
                formHtml += `<span class="${className}">${reversedForm[i]}</span>`;
            }
            formElement.innerHTML = formHtml;
            tableFormElement.innerHTML = formHtml;
            formElement.classList.add('updated');
            tableFormElement.classList.add('updated');
            setTimeout(() => {
                formElement.classList.remove('updated');
                tableFormElement.classList.remove('updated');
            }, 2000);

            let historyHtml = '';
            if (isHome) {
                historyHtml += `<p><strong>${data.teamName} Recent Results:</strong></p><ul>`;
                data.results.forEach(result => historyHtml += `<li>${result}</li>`);
                historyHtml += `</ul><div class='standings'>
                    <span>POS: ${data.standings.position || 'N/A'}</span>
                    <span>GS: ${data.standings.goalsScored || 'N/A'}</span>
                    <span>GD: ${data.standings.goalDifference || 'N/A'}</span>
                    <span>PTS: ${data.standings.points || 'N/A'}</span>
                </div>`;
                historyElement.innerHTML = historyHtml + historyElement.innerHTML;
            } else {
                historyHtml = historyElement.innerHTML.replace('Loading history...', '');
                historyHtml += `<p><strong>${data.teamName} Recent Results:</strong></p><ul>`;
                data.results.forEach(result => historyHtml += `<li>${result}</li>`);
                historyHtml += `</ul><div class='standings'>
                    <span>POS: ${data.standings.position || 'N/A'}</span>
                    <span>GS: ${data.standings.goalsScored || 'N/A'}</span>
                    <span>GD: ${data.standings.goalDifference || 'N/A'}</span>
                    <span>PTS: ${data.standings.points || 'N/A'}</span>
                </div>`;
                historyElement.innerHTML = historyHtml;
            }
        }

        function fetchPrediction(index, homeId, awayId, attempt = 0, maxAttempts = 10) {
            const delay = Math.min(Math.pow(2, attempt) * 1000, 10000);
            const predictionElement = document.getElementById(`prediction-${index}`);
            const tablePrediction = document.getElementById(`table-prediction-${index}`);
            const tableConfidence = document.getElementById(`table-confidence-${index}`);
            const tablePredictedScore = document.getElementById(`table-predicted-score-${index}`);
            const progressBar = predictionElement.querySelector('.progress-fill') || document.createElement('div');

            if (!progressBar.classList.contains('progress-fill')) {
                progressBar.classList.add('progress-fill');
                const progressContainer = document.createElement('div');
                progressContainer.classList.add('progress-bar');
                progressContainer.appendChild(progressBar);
                predictionElement.appendChild(progressContainer);
            }

            if (attempt === 5) {
                const retryNotice = document.createElement('div');
                retryNotice.className = 'retry-notice';
                retryNotice.innerHTML = 'Still predicting match outcome. Please wait...';
                retryNotice.style.cssText = `
                    background-color: #fff3cd; 
                    border: 1px solid #ffeeba; 
                    color: #856404; 
                    padding: 10px; 
                    margin-top: 10px; 
                    border-radius: 5px; 
                    text-align: center;
                    font-size: 0.9em;
                `;
                predictionElement.appendChild(retryNotice);
                setTimeout(() => retryNotice.remove(), 5000);
            }

            fetch(`?action=predict_match&homeId=${homeId}&awayId=${awayId}&competition=<?php echo $selectedComp; ?>&attempt=${attempt}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    predictionElement.innerHTML = `
                        <p>Prediction: ${data.prediction} <span class="result-indicator">${data.resultIndicator}</span></p>
                        <p class="predicted-score">Predicted Score: ${data.predictedScore}</p>
                        <p class="confidence">Confidence: ${data.confidence}</p>
                        <p class="advantage advantage-${data.advantage.toLowerCase().replace(' ', '-')}">${data.advantage}</p>
                    `;
                    tablePrediction.innerHTML = `${data.prediction} ${data.resultIndicator}`;
                    tableConfidence.innerHTML = data.confidence;
                    tablePredictedScore.innerHTML = data.predictedScore;
                    const matchCard = document.querySelector(`.match-card[data-index="${index}"]`);
                    applyAdvantageHighlight(matchCard, data.advantage);
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                } else if (data.retry && attempt < maxAttempts) {
                    progressBar.style.width = `${(attempt + 1) / maxAttempts * 100}%`;
                    setTimeout(() => fetchPrediction(index, homeId, awayId, attempt + 1, maxAttempts), delay);
                } else {
                    console.error(`Max retries reached for prediction ${index}`);
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                }
            })
            .catch(error => {
                console.error('Error fetching prediction:', error);
                if (attempt < maxAttempts) {
                    progressBar.style.width = `${(attempt + 1) / maxAttempts * 100}%`;
                    setTimeout(() => fetchPrediction(index, homeId, awayId, attempt + 1, maxAttempts), delay);
                } else {
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                }
            });
        }

        function applyAdvantageHighlight(matchCard, advantage) {
            const homeTeam = matchCard.querySelector('.teams .team:first-child');
            const awayTeam = matchCard.querySelector('.teams .team:last-child');
            
            homeTeam.classList.remove('home-advantage');
            awayTeam.classList.remove('away-advantage');
            matchCard.classList.remove('draw-likely');

            if (advantage === 'Home Advantage') {
                homeTeam.classList.add('home-advantage');
            } else if (advantage === 'Away Advantage') {
                awayTeam.classList.add('away-advantage');
            } else if (advantage === 'Likely Draw') {
                matchCard.classList.add('draw-likely');
            }
        }

        function startMatchPolling() {
            setInterval(() => {
                document.querySelectorAll('.match-card, .match-table tr').forEach(element => {
                    const homeId = element.dataset.homeId;
                    const awayId = element.dataset.awayId;
                    const index = element.dataset.index;
                    const status = element.dataset.status;
                    const matchInfo = element.classList.contains('match-card') 
                        ? element.querySelector('.match-info p').textContent 
                        : element.cells[0].textContent;

                    if (matchInfo.includes('finished') && element.querySelector('.result-indicator')) return;

                    fetch(`?action=predict_match&homeId=${homeId}&awayId=${awayId}&competition=<?php echo $selectedComp; ?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (element.classList.contains('match-card')) {
                                const predictionElement = document.getElementById(`prediction-${index}`);
                                const homeFormElement = document.getElementById(`form-home-${index}`);
                                const awayFormElement = document.getElementById(`form-away-${index}`);
                                const matchInfoElement = element.querySelector('.match-info p');

                                predictionElement.innerHTML = `
                                    <p>Prediction: ${data.prediction} <span class="result-indicator">${data.resultIndicator}</span></p>
                                    <p class="predicted-score">Predicted Score: ${data.predictedScore}</p>
                                    <p class="confidence">Confidence: ${data.confidence}</p>
                                    <p class="advantage advantage-${data.advantage.toLowerCase().replace(' ', '-')}">${data.advantage}</p>
                                `;
                                applyAdvantageHighlight(element, data.advantage);

                                if (data.resultIndicator) {
                                    element.dataset.status = 'finished';
                                    const currentText = matchInfoElement.textContent.split(' - ')[0];
                                    fetch(`?action=fetch_team_data&teamId=${homeId}&competition=<?php echo $selectedComp; ?>&force_refresh=true`)
                                        .then(res => res.json())
                                        .then(homeData => {
                                            const homeGoals = homeData.results[0]?.match(/(\d+) - (\d+)/)?.[1] || 'N/A';
                                            const awayGoals = homeData.results[0]?.match(/(\d+) - (\d+)/)?.[2] || 'N/A';
                                            matchInfoElement.textContent = `${currentText} - ${homeGoals} : ${awayGoals}`;
                                        });

                                    const homeForm = data.homeForm.slice(-6).padStart(6, '-').split('').reverse().join('');
                                    let homeFormHtml = '';
                                    for (let i = 0; i < 6; i++) {
                                        let className = homeForm[i] === 'W' ? 'win' : (homeForm[i] === 'D' ? 'draw' : (homeForm[i] === 'L' ? 'loss' : 'empty'));
                                        if (i === 5 && homeForm[i] !== '-' && data.homeForm.trim('-').length > 0) className += ' latest';
                                        homeFormHtml += `<span class="${className}">${homeForm[i]}</span>`;
                                    }
                                    homeFormElement.innerHTML = homeFormHtml;
                                    homeFormElement.dataset.form = data.homeForm;

                                    const awayForm = data.awayForm.slice(-6).padStart(6, '-').split('').reverse().join('');
                                    let awayFormHtml = '';
                                    for (let i = 0; i < 6; i++) {
                                        let className = awayForm[i] === 'W' ? 'win' : (awayForm[i] === 'D' ? 'draw' : (awayForm[i] === 'L' ? 'loss' : 'empty'));
                                        if (i === 5 && awayForm[i] !== '-' && data.awayForm.trim('-').length > 0) className += ' latest';
                                        awayFormHtml += `<span class="${className}">${awayForm[i]}</span>`;
                                    }
                                    awayFormElement.innerHTML = awayFormHtml;
                                    awayFormElement.dataset.form = data.awayForm;

                                    [homeFormElement, awayFormElement].forEach(el => {
                                        el.classList.add('updated');
                                        setTimeout(() => el.classList.remove('updated'), 2000);
                                    });
                                }
                            } else {
                                const tablePrediction = document.getElementById(`table-prediction-${index}`);
                                const tableConfidence = document.getElementById(`table-confidence-${index}`);
                                const tablePredictedScore = document.getElementById(`table-predicted-score-${index}`);
                                const tableHomeForm = document.getElementById(`table-form-home-${index}`);
                                const tableAwayForm = document.getElementById(`table-form-away-${index}`);

                                tablePrediction.innerHTML = `${data.prediction} ${data.resultIndicator}`;
                                tableConfidence.innerHTML = data.confidence;
                                tablePredictedScore.innerHTML = data.predictedScore;

                                if (data.resultIndicator) {
                                    element.dataset.status = 'finished';
                                    element.cells[2].textContent = `${data.homeGoals || 'N/A'} - ${data.awayGoals || 'N/A'}`;

                                    const homeForm = data.homeForm.slice(-6).padStart(6, '-');
                                    let homeFormHtml = '';
                                    const homeFormLength = data.homeForm.trim('-').length;
                                    for (let i = 0; i < 6; i++) {
                                        let className = homeForm[i] === 'W' ? 'win' : (homeForm[i] === 'D' ? 'draw' : (homeForm[i] === 'L' ? 'loss' : 'empty'));
                                        if (i === homeFormLength - 1 && homeForm[i] !== '-' && homeFormLength > 0) className += ' latest';
                                        homeFormHtml += `<span class="${className}">${homeForm[i]}</span>`;
                                    }
                                    tableHomeForm.innerHTML = homeFormHtml;

                                    const awayForm = data.awayForm.slice(-6).padStart(6, '-');
                                    let awayFormHtml = '';
                                    const awayFormLength = data.awayForm.trim('-').length;
                                    for (let i = 0; i < 6; i++) {
                                        let className = awayForm[i] === 'W' ? 'win' : (awayForm[i] === 'D' ? 'draw' : (awayForm[i] === 'L' ? 'loss' : 'empty'));
                                        if (i === awayFormLength - 1 && awayForm[i] !== '-' && awayFormLength > 0) className += ' latest';
                                        awayFormHtml += `<span class="${className}">${awayForm[i]}</span>`;
                                    }
                                    tableAwayForm.innerHTML = awayFormHtml;

                                    element.cells[7].textContent = `${data.homeForm} / ${data.awayForm}`;
                                }
                            }
                        }
                    })
                    .catch(error => console.error('Polling error:', error));
                });
            }, 60000);
        }

        const searchInput = document.querySelector('.search-input');
        const autocompleteDropdown = document.querySelector('.autocomplete-dropdown');
        const searchContainer = document.querySelector('.search-container');
        let debounceTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const query = this.value.trim();
                if (query.length < 2) {
                    autocompleteDropdown.innerHTML = '';
                    searchContainer.classList.remove('active');
                    return;
                }

                fetch(`?action=search_teams&query=${encodeURIComponent(query)}&competition=<?php echo $selectedComp; ?>`)
                .then(response => response.json())
                .then(teams => {
                    if (teams.length === 0) {
                        autocompleteDropdown.innerHTML = '<div class="autocomplete-item">No teams found</div>';
                    } else {
                        autocompleteDropdown.innerHTML = teams.map(team => `
                            <div class="autocomplete-item" data-team-id="${team.id}" data-team-name="${team.name}">
                                ${team.crest ? `<img src="${team.crest}" alt="${team.name}">` : ''}
                                <span>${team.name}</span>
                            </div>
                        `).join('');
                    }
                    searchContainer.classList.add('active');
                })
                .catch(error => {
                    console.error('Error fetching teams:', error);
                    autocompleteDropdown.innerHTML = '<div class="autocomplete-item">Error loading teams</div>';
                    searchContainer.classList.add('active');
                });
            }, 300);
        });

        autocompleteDropdown.addEventListener('click', function(e) {
            const item = e.target.closest('.autocomplete-item');
            if (item && item.dataset.teamName) {
                searchInput.value = item.dataset.teamName;
                searchContainer.classList.remove('active');
                window.location.href = `?competition=<?php echo $selectedComp; ?>&filter=<?php echo $filter; ?>&team=${encodeURIComponent(item.dataset.teamName)}`;
            }
        });

        document.addEventListener('click', function(e) {
            if (!searchContainer.contains(e.target)) {
                searchContainer.classList.remove('active');
            }
        });

        function generateUniqueFilename() {
            const now = new Date();
            const timestamp = now.toISOString().replace(/[-:T]/g, '').split('.')[0];
            const randomStr = Math.random().toString(36).substring(2, 6);
            return `CPS#manu_${timestamp}_${randomStr}.png`;
        }

        function shareTableAsImage() {
            const tableElement = document.querySelector('#match-table table');
            const shareBtn = document.getElementById('share-table-btn');
            
            shareBtn.disabled = true;
            shareBtn.innerHTML = '<span class="share-icon">⏳</span> Processing...';

            html2canvas(tableElement, {
                backgroundColor: getComputedStyle(document.body).getPropertyValue('--card-bg'),
                scale: 2
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const fileName = generateUniqueFilename();

                if (navigator.share && navigator.canShare && navigator.canShare({ files: [] })) {
                    canvas.toBlob(blob => {
                        const file = new File([blob], fileName, { type: 'image/png' });
                        navigator.share({
                            title: 'CPS Football Predictions',
                            text: 'Check out these football match predictions!',
                            files: [file]
                        }).then(() => console.log('Table shared successfully'))
                          .catch(err => {
                              console.error('Share failed:', err);
                              fallbackDownload(imgData, fileName);
                          });
                    });
                } else {
                    fallbackDownload(imgData, fileName);
                }
            }).catch(error => {
                console.error('Error generating image:', error);
                alert('Failed to generate table image. Please try again.');
            }).finally(() => {
                shareBtn.disabled = false;
                shareBtn.innerHTML = '<span class="share-icon">📤</span> Share';
            });
        }

        function fallbackDownload(imgData, fileName) {
            const link = document.createElement('a');
            link.href = imgData;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        document.getElementById('share-table-btn').addEventListener('click', shareTableAsImage);

        document.getElementById('table-view-btn').addEventListener('click', function() {
            switchView('table');
            setTimeout(() => {
                const table = document.getElementById('match-table');
                if (table.style.display !== 'none') {
                    table.scrollIntoView({ behavior: 'smooth' });
                }
            }, 100);
        });

        function adjustTeamSpacing() {
            document.querySelectorAll('.match-card').forEach(card => {
                const teamsContainer = card.querySelector('.teams');
                const homeTeam = card.querySelector('.home-team');
                const awayTeam = card.querySelector('.away-team');
                const vsElement = card.querySelector('.vs');
                const cardWidth = card.offsetWidth;

                const vsPadding = Math.max(5, cardWidth * 0.03);
                vsElement.style.padding = `0 ${vsPadding}px`;

                const homeTextWidth = homeTeam.querySelector('p').scrollWidth;
                const awayTextWidth = awayTeam.querySelector('p').scrollWidth;
                const maxTextWidth = Math.max(homeTextWidth, awayTextWidth);
                const extraPadding = Math.min(10, maxTextWidth * 0.05);
                homeTeam.style.paddingRight = `${0.5 + extraPadding / 16}em`;
                awayTeam.style.paddingLeft = `${0.5 + extraPadding / 16}em`;
            });
        }

        window.onload = function() {
            const theme = document.cookie.split('; ')
                .find(row => row.startsWith('theme='))
                ?.split('=')[1];
            const themeIcon = document.querySelector('.theme-icon');
            if (theme) {
                document.body.setAttribute('data-theme', theme);
                document.querySelectorAll('.match-info').forEach(el => el.classList.toggle('dark', theme === 'dark'));
                themeIcon.textContent = theme === 'dark' ? '☀️' : '🌙';
            } else {
                themeIcon.textContent = '🌙';
            }

            const currentFilter = '<?php echo $filter; ?>';
            document.querySelectorAll('.filter-option').forEach(option => {
                option.classList.toggle('selected', option.getAttribute('data-filter') === currentFilter);
            });

            if (currentFilter === 'custom') {
                document.querySelector('.custom-date-range').classList.add('active');
            }

            document.querySelectorAll('.match-card').forEach(matchCard => {
                const advantage = matchCard.dataset.advantage;
                if (advantage && !matchCard.querySelector('.prediction').innerHTML.includes('Loading')) {
                    applyAdvantageHighlight(matchCard, advantage);
                }
            });

            const savedView = localStorage.getItem('matchView') || 'grid';
            switchView(savedView);

            adjustTeamSpacing();
            window.addEventListener('resize', adjustTeamSpacing);
            startMatchPolling();

            if (typeof incompleteTeams !== 'undefined' && incompleteTeams.length > 0) {
                incompleteTeams.forEach(teamId => {
                    document.querySelectorAll(`.match-card[data-home-id="${teamId}"], .match-card[data-away-id="${teamId}"]`).forEach(card => {
                        const index = card.dataset.index;
                        const homeId = card.dataset.homeId;
                        const awayId = card.dataset.awayId;

                        if (homeId == teamId) fetchTeamData(homeId, index, true);
                        if (awayId == teamId) fetchTeamData(awayId, index, false);
                    });
                });
            }
        }
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    handleError("Unexpected error: " . $e->getMessage());
}
?>
<?php include 'back-to-top.php'; ?>
<script src="network-status.js"></script>
<script src="tab-title-switcher.js"></script>
<?php include 'global-footer.php'; ?>
