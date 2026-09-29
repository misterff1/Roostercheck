document.addEventListener('DOMContentLoaded', () => {
    const loginScreen = document.getElementById('login-screen');
    const scheduleScreen = document.getElementById('schedule-screen');
    const logoutBtn = document.getElementById('logout-btn');
    const displayStudent = document.getElementById('display-student');
    const nextLessonContainer = document.getElementById('next-lesson');
    const todayLessonsList = document.getElementById('today-lessons-list');
    const tomorrowLessonsList = document.getElementById('tomorrow-lessons-list');
    const messageContainer = document.getElementById('message-container');
    const tomorrowDivider = document.getElementById('tomorrow-divider');
    const timerText = document.getElementById('timer-text');
    const timerProgress = document.querySelector('.timer-progress');

    const pinDigits = document.querySelectorAll('.pin-digit');
    const maxPinLength = pinDigits.length || 6;
    const numBtns = document.querySelectorAll('.num-btn[data-num]');
    const revertBtn = document.getElementById('revert-btn');
    const pinDisplay = document.getElementById('pin-display');
    const numpad = document.querySelector('.numpad');
    const loadingIndicator = document.getElementById('loading-indicator');
    
    const headerSentinel = document.getElementById('header-sentinel');
    const header = document.querySelector('.header');

    const appElement = document.getElementById('app');
    const pinResetTimeoutSec = parseInt(appElement?.dataset?.pinResetTimeout, 10) || 15;
    const LOGOUT_TIME = parseInt(appElement?.dataset?.logoutTimeout, 10) || 20;

    let logoutTimer = null;
    let countdownInterval = null;
    
    let currentPin = '';
    let loginTimeout = null;

    if (headerSentinel && header) {
        const observer = new IntersectionObserver((entries) => {
            const entry = entries[0];
            if (!entry.isIntersecting && entry.boundingClientRect.top < 0) {
                header.classList.add('stuck');
            } else {
                header.classList.remove('stuck');
            }
        }, {
            threshold: [1],
            rootMargin: '0px 0px 0px 0px'
        });
        
        observer.observe(headerSentinel);
    }

    function resetLoginTimeout() {
        clearTimeout(loginTimeout);
        loginTimeout = setTimeout(() => {
            currentPin = '';
            updatePinDisplay();
        }, pinResetTimeoutSec * 1000);
    }

    numBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            btn.blur();
            resetLoginTimeout();
            if (currentPin.length < maxPinLength) {
                currentPin += btn.dataset.num;
                updatePinDisplay();
                
                if (currentPin.length === maxPinLength) {
                    clearTimeout(loginTimeout);
                    initiateLogin(currentPin);
                }
            }
        });
    });

    revertBtn.addEventListener('click', () => {
        revertBtn.blur();
        resetLoginTimeout();
        if (currentPin.length > 0) {
            currentPin = currentPin.slice(0, -1);
            updatePinDisplay();
        }
    });

    function updatePinDisplay() {
        pinDigits.forEach((digit, index) => {
            if (index < currentPin.length) {
                digit.textContent = currentPin[index];
                digit.classList.add('filled');
            } else {
                digit.textContent = '';
                digit.classList.remove('filled');
            }
        });
    }

    async function initiateLogin(student) {
        pinDisplay.classList.add('hidden');
        numpad.classList.add('hidden');
        loadingIndicator.classList.remove('hidden');

        await new Promise(resolve => setTimeout(resolve, 500));

        try {
            const response = await fetch(`api.php?student=${encodeURIComponent(student)}`);
            const data = await response.json();

            if (data.error) {
                alert(data.error);
                resetLoginScreen();
                return;
            }

            renderSchedule(data);
            showScreen('schedule');
            startLogoutSequence();
        } catch (error) {
            console.error('Fetch error:', error);
            alert('Kon rooster niet ophalen. Probeer het later opnieuw.');
            resetLoginScreen();
        }
    }

    function resetLoginScreen() {
        currentPin = '';
        updatePinDisplay();
        pinDisplay.classList.remove('hidden');
        numpad.classList.remove('hidden');
        loadingIndicator.classList.add('hidden');
        clearTimeout(loginTimeout);
    }

    logoutBtn.addEventListener('click', logout);

    function logout() {
        clearTimeout(logoutTimer);
        clearInterval(countdownInterval);
        resetLoginScreen();
        showScreen('login');
    }

    function startLogoutSequence() {
        clearTimeout(logoutTimer);
        clearInterval(countdownInterval);

        let timeLeft = LOGOUT_TIME;
        updateTimerUI(timeLeft);

        countdownInterval = setInterval(() => {
            timeLeft--;
            updateTimerUI(timeLeft);
            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                logout();
            }
        }, 1000);
    }

    function updateTimerUI(seconds) {
        timerText.textContent = seconds;
        const offset = 113.1 - (seconds / LOGOUT_TIME) * 113.1;
        timerProgress.style.strokeDashoffset = offset;
    }

    function showScreen(screen) {
        if (screen === 'login') {
            loginScreen.classList.remove('hidden');
            scheduleScreen.classList.add('hidden');
        } else {
            loginScreen.classList.add('hidden');
            scheduleScreen.classList.remove('hidden');
        }
    }

    function renderSchedule(data) {
        displayStudent.textContent = data.student;
        
        const now = data.server_time ? data.server_time : Math.floor(Date.now() / 1000);
        let nextLessonRendered = false;
        let isNu = false;
        
        if (data.next) {
            isNu = now >= data.next.start && now < data.next.end;
            let cssClass = isNu ? 'nu' : 'volgende';
            
            nextLessonContainer.innerHTML = createLessonHTML(data.next, cssClass);
            nextLessonRendered = true;
        } else {
            nextLessonContainer.innerHTML = '';
        }

        todayLessonsList.innerHTML = '';
        
        let futureTodayLessons = data.today.filter(lesson => {
            const isAlreadyShown = data.next && lesson.id === data.next.id;
            const isPast = lesson.end <= now;
            return !isAlreadyShown && !isPast;
        });

        if (futureTodayLessons.length > 0) {
            futureTodayLessons.forEach(lesson => {
                todayLessonsList.innerHTML += createLessonHTML(lesson);
            });
            messageContainer.classList.add('hidden');
        } else {
            if (data.today.length > 0 || nextLessonRendered) {
                if (!isNu) {
                    messageContainer.textContent = 'Geen verdere lessen voor vandaag';
                    messageContainer.classList.remove('hidden');
                } else {
                    messageContainer.classList.add('hidden');
                }
            } else {
                if (data.message) {
                    messageContainer.textContent = data.message;
                    messageContainer.classList.remove('hidden');
                } else {
                    messageContainer.classList.add('hidden');
                }
            }
        }

        tomorrowLessonsList.innerHTML = '';
        if (data.tomorrow && data.tomorrow.length > 0) {
            tomorrowDivider.classList.remove('hidden');
            data.tomorrow.forEach(lesson => {
                tomorrowLessonsList.innerHTML += createLessonHTML(lesson);
            });
        } else {
            tomorrowDivider.classList.add('hidden');
        }
    }

    function createLessonHTML(lesson, customClass = '') {
        let isFullyCancelledClass = lesson.isFullyCancelled ? 'fully-cancelled' : '';
        let isDeviatingClass = lesson.isDeviating ? 'deviating' : '';
        
        let displayLabel = lesson.label || '';
        if (displayLabel) {
            const match = displayLabel.match(/\d+/);
            if (match) {
                displayLabel = `${match[0]}<sup>e</sup>`;
            }
        }
        
        let locationHTML = lesson.locations ? `<span>📍 ${lesson.locations}</span>` : '';
        if (lesson.oldLocations) {
            locationHTML = `
                <span>📍 
                    <span class="old-value">${lesson.oldLocations}</span>
                    <span class="highlight-value">${lesson.locations}</span>
                </span>
            `;
        }

        let teacherHTML = lesson.teachers ? `<span>👤 ${lesson.teachers}</span>` : '';
        if (lesson.oldTeachers) {
            teacherHTML = `
                <span>👤 
                    <span class="old-value">${lesson.oldTeachers}</span>
                    <span class="highlight-value">${lesson.teachers}</span>
                </span>
            `;
        }

        let detailsHTML = '';
        if (locationHTML || teacherHTML) {
            detailsHTML = `
                <div class="lesson-details">
                    ${locationHTML}
                    ${teacherHTML}
                </div>
            `;
        }

        return `
            <div class="lesson-card ${customClass} ${isDeviatingClass} ${isFullyCancelledClass}">
                <div class="lesson-time-container">
                    <div class="lesson-hour">${displayLabel}</div>
                    <div class="lesson-time">${lesson.time || ''}</div>
                </div>
                <div class="lesson-info">
                    <div class="lesson-subject">${lesson.subjects}</div>
                    ${detailsHTML}
                </div>
            </div>
        `;
    }
});
