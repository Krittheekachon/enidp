<script setup>
import { Head } from '@inertiajs/vue3';

const loginWithPassword = () => {
    window.location.href = route('login');
};
</script>

<template>
    <Head :title="$page.props.pageTitle || 'เข้าสู่ระบบ'" />

    <main class="welcome-page">
        <header class="top-bar" aria-label="Faculty branding">
            <div class="top-brand" aria-label="EN-IDP Competency and IDP System">
                <svg class="top-brand-mark" viewBox="40 58 470 210" aria-hidden="true" focusable="false">
                    <g fill="#8f3036">
                        <path d="M185 65 47 127v135h153v-29H79v-24l106-47v-31L79 178v-29l106-47z" />
                        <path d="M205 65h23l105 122V109l28-13v166h-30L233 149v113h-28z" />
                    </g>
                    <g fill="none" stroke="#8f3036" stroke-width="4" stroke-linejoin="miter" stroke-linecap="square">
                        <path d="M375 261V119l53-25v168m-38-8V130l22-10v135m16-95 22 10v92m-22-50 72 41v9" />
                    </g>
                </svg>

                <span class="top-brand-divider" aria-hidden="true"></span>

                <span class="top-brand-text">
                    <strong>EN-IDP</strong>
                    <span>Competency &amp; IDP System</span>
                </span>
            </div>

            <p>Faculty of Engineering <span>|</span> Khon Kaen University</p>
        </header>

        <section class="hero" aria-labelledby="welcome-title">
            <div class="hero-logo" aria-label="EN-IDP Competency and IDP System">
                <svg class="hero-logo-mark" viewBox="40 58 470 210" aria-hidden="true" focusable="false">
                    <g fill="#8f3036">
                        <path d="M185 65 47 127v135h153v-29H79v-24l106-47v-31L79 178v-29l106-47z" />
                        <path d="M205 65h23l105 122V109l28-13v166h-30L233 149v113h-28z" />
                    </g>
                    <g fill="none" stroke="#8f3036" stroke-width="4" stroke-linejoin="miter" stroke-linecap="square">
                        <path d="M375 261V119l53-25v168m-38-8V130l22-10v135m16-95 22 10v92m-22-50 72 41v9" />
                    </g>
                </svg>

                <strong>EN-IDP</strong>
                <span>Competency &amp; IDP System</span>
            </div>

            <h1 id="welcome-title">ระบบบริหารสมรรถนะและแผนพัฒนารายบุคคล</h1>
            <p class="subtitle">Competency &amp; Individual Development Plan Management System</p>

            <div class="login-actions">
                <button class="login-button" type="button" disabled aria-disabled="true">
                    <span class="login-icon" aria-hidden="true">
                        <span></span>
                    </span>
                    <span>เข้าสู่ระบบด้วย KKU SSO</span>
                </button>

                <button class="alternative-login-button" type="button" @click="loginWithPassword">
                    หรือ เข้าสู่ระบบด้วยวิธีอื่น
                </button>
            </div>

            <div class="support-row">
                <span></span>
                <p>พบปัญหาการเข้าใช้งาน ติดต่อหน่วยงานทรัพยากรบุคคลคณะวิศวกรรมศาสตร์</p>
                <span></span>
            </div>
        </section>

        <div class="campus-line" aria-hidden="true"></div>
    </main>
</template>

<style scoped>
.welcome-page {
    position: relative;
    isolation: isolate;
    height: 100vh;
    max-height: 100svh;
    overflow: hidden;
    background:
        radial-gradient(circle at 50% 10%, rgba(255, 255, 255, 0.045), transparent 18%),
        radial-gradient(circle at 52% 55%, rgba(117, 41, 45, 0.1), transparent 34%),
        url('/images/en-idp-bg.png'),
        linear-gradient(180deg, #101719 0%, #101719 54%, #0f1517 100%);
    background-position:
        center center,
        center center,
        center bottom,
        center center;
    background-repeat: no-repeat;
    background-size:
        auto,
        auto,
        100% 100%,
        auto;
    color: #ffffff;
    font-family: 'Kanit', 'Noto Sans Thai', system-ui, sans-serif;
}

.welcome-page::before {
    position: absolute;
    z-index: 0;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.035), transparent 18%);
    content: '';
    opacity: 0.72;
}

.welcome-page::after {
    position: absolute;
    z-index: 0;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(ellipse at center, transparent 38%, rgba(0, 0, 0, 0.36) 100%),
        linear-gradient(180deg, rgba(16, 23, 25, 0) 0%, rgba(16, 23, 25, 0.28) 72%, rgba(16, 23, 25, 0.76) 100%);
    content: '';
    display: none;
}

.top-bar {
    position: relative;
    z-index: 3;
    display: flex;
    height: 66px;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.22);
    padding: 10px clamp(20px, 4.8vw, 78px);
}

.top-brand {
    display: inline-flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
}

.top-brand-mark {
    display: block;
    width: 86px;
    height: 42px;
    overflow: visible;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.18));
}

.top-brand-divider {
    width: 1px;
    height: 38px;
    background: rgba(255, 255, 255, 0.78);
}

.top-brand-text {
    display: grid;
    gap: 4px;
    color: #ffffff;
    text-align: left;
    text-transform: uppercase;
}

.top-brand-text strong {
    font-size: 27px;
    font-weight: 800;
    line-height: 0.9;
}

.top-brand-text span {
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
}

.top-bar p {
    margin: 0;
    color: rgba(255, 255, 255, 0.9);
    font-size: clamp(10px, 0.8vw, 14px);
    font-weight: 500;
    text-transform: uppercase;
    white-space: nowrap;
}

.top-bar p span {
    display: inline-block;
    margin-inline: 12px;
    color: rgba(255, 255, 255, 0.72);
}

.hero {
    position: relative;
    z-index: 2;
    display: flex;
    box-sizing: border-box;
    height: calc(100% - 66px);
    flex-direction: column;
    align-items: center;
    padding: clamp(8px, 1.4vh, 16px) 22px clamp(174px, 26vh, 260px);
    text-align: center;
}

.hero > * {
    min-width: 0;
}

.hero-logo {
    display: grid;
    justify-items: center;
    width: min(360px, calc(100vw - 40px));
    margin-bottom: 22px;
    color: #ffffff;
    text-align: center;
    text-transform: uppercase;
    filter: drop-shadow(0 14px 18px rgba(0, 0, 0, 0.4));
}

.hero-logo-mark {
    display: block;
    width: 170px;
    height: 88px;
    overflow: visible;
}

.hero-logo strong {
    margin-top: -6px;
    font-size: 50px;
    font-weight: 800;
    line-height: 0.9;
}

.hero-logo span {
    margin-top: 14px;
    font-size: 16px;
    font-weight: 500;
    line-height: 1;
}

.hero h1 {
    margin: 0;
    max-width: min(980px, calc(100vw - 32px));
    font-size: clamp(27px, 3.35vw, 48px);
    font-weight: 900;
    line-height: 1.18;
    text-shadow: 0 5px 14px rgba(0, 0, 0, 0.36);
}

.subtitle {
    max-width: min(980px, calc(100vw - 32px));
    margin: 10px 0 0;
    color: rgba(255, 255, 255, 0.9);
    font-size: clamp(11px, 1.1vw, 19px);
    font-weight: 400;
    line-height: 1.35;
    text-transform: uppercase;
}

.login-actions {
    display: grid;
    width: min(500px, calc(100vw - 44px));
    gap: 12px;
    margin-top: clamp(22px, 3.8vh, 34px);
}

.login-button {
    display: inline-flex;
    width: 100%;
    min-height: 58px;
    align-items: center;
    justify-content: center;
    gap: 20px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.09), transparent),
        linear-gradient(90deg, #8e2f36 0%, #a43a43 48%, #812930 100%);
    box-shadow: 0 18px 34px rgba(0, 0, 0, 0.32);
    color: #ffffff;
    cursor: pointer;
    font-size: clamp(16px, 1.25vw, 23px);
    font-weight: 800;
    line-height: 1.2;
    padding: 12px 22px;
    transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
}

.login-button:hover {
    box-shadow: 0 22px 40px rgba(0, 0, 0, 0.4);
    filter: brightness(1.06);
    transform: translateY(-1px);
}

.login-button:disabled {
    cursor: not-allowed;
    opacity: 0.76;
}

.login-button:disabled:hover {
    box-shadow: 0 18px 34px rgba(0, 0, 0, 0.32);
    filter: none;
    transform: none;
}

.login-button:focus-visible {
    outline: 3px solid rgba(255, 255, 255, 0.7);
    outline-offset: 4px;
}

.login-icon {
    position: relative;
    flex: 0 0 auto;
    width: 35px;
    height: 35px;
    border: 2px solid rgba(255, 255, 255, 0.94);
    border-left-color: transparent;
    border-radius: 7px;
}

.login-icon::before,
.login-icon::after,
.login-icon span::before {
    position: absolute;
    display: block;
    background: #ffffff;
    content: '';
}

.alternative-login-button {
    width: 100%;
    min-height: 48px;
    border: 1px solid rgba(255, 255, 255, 0.72);
    border-radius: 6px;
    background: rgba(15, 21, 23, 0.36);
    color: #ffffff;
    cursor: pointer;
    font: inherit;
    font-size: clamp(14px, 1vw, 17px);
    font-weight: 600;
    line-height: 1.3;
    padding: 11px 20px;
    transition: background-color 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
}

.alternative-login-button:hover {
    border-color: #ffffff;
    background: rgba(255, 255, 255, 0.12);
    transform: translateY(-1px);
}

.alternative-login-button:focus-visible {
    outline: 3px solid rgba(255, 255, 255, 0.7);
    outline-offset: 4px;
}

.login-icon::before {
    top: 16px;
    left: -8px;
    width: 25px;
    height: 2px;
}

.login-icon::after {
    top: 11px;
    left: 8px;
    width: 13px;
    height: 13px;
    border-top: 2px solid #ffffff;
    border-right: 2px solid #ffffff;
    background: transparent;
    transform: rotate(45deg);
}

.login-icon span::before {
    top: 7px;
    left: -8px;
    width: 13px;
    height: 2px;
    box-shadow: 0 18px 0 #ffffff;
}

.support-row {
    display: grid;
    width: min(720px, calc(100vw - 42px));
    grid-template-columns: minmax(48px, 1fr) auto minmax(48px, 1fr);
    align-items: center;
    gap: 18px;
    margin-top: 30px;
}

.support-row span {
    height: 1px;
    background: rgba(255, 255, 255, 0.6);
}

.support-row p {
    margin: 0;
    color: rgba(255, 255, 255, 0.84);
    font-size: clamp(10px, 0.82vw, 13px);
    line-height: 1.5;
}

.campus-line {
    display: none;
}

@media (max-width: 860px) {
    .top-bar {
        height: 60px;
    }

    .top-bar p {
        display: none;
    }

    .hero {
        height: calc(100% - 60px);
        padding-top: 8px;
        padding-bottom: clamp(150px, 25vh, 210px);
    }

    .login-button {
        min-height: 54px;
        gap: 14px;
    }

    .login-actions {
        gap: 10px;
    }

    .support-row {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .support-row span {
        display: none;
    }
}

@media (max-width: 520px) {
    .top-bar {
        padding-inline: 18px;
    }

    .top-brand {
        gap: 9px;
    }

    .top-brand-mark {
        width: 66px;
        height: 32px;
    }

    .top-brand-divider {
        height: 30px;
    }

    .top-brand-text strong {
        font-size: 20px;
    }

    .top-brand-text span {
        font-size: 8px;
    }

    .hero {
        padding-inline: 16px;
        padding-top: 6px;
        padding-bottom: 142px;
    }

    .hero-logo {
        width: min(230px, calc(100vw - 32px));
        margin-bottom: 12px;
    }

    .hero-logo-mark {
        width: 134px;
        height: 60px;
    }

    .hero-logo strong {
        font-size: 34px;
    }

    .hero-logo span {
        margin-top: 10px;
        font-size: 10px;
    }

    .hero h1 {
        max-width: 100%;
        font-size: clamp(22px, 6.8vw, 28px);
        line-height: 1.25;
        overflow-wrap: anywhere;
        white-space: normal;
        word-break: break-all;
    }

    .subtitle {
        max-width: 100%;
        font-size: 10px;
        overflow-wrap: anywhere;
        white-space: normal;
        word-break: break-word;
    }

    .login-button {
        min-height: 50px;
        gap: 12px;
        padding-inline: 16px;
    }

    .login-actions {
        margin-top: 18px;
    }

    .alternative-login-button {
        min-height: 44px;
        padding: 9px 14px;
    }

    .support-row {
        margin-top: 24px;
    }

    .support-row p {
        max-width: 100%;
        overflow-wrap: anywhere;
        white-space: normal;
        word-break: break-all;
    }

    .login-icon {
        width: 31px;
        height: 31px;
    }

    .login-icon::before {
        top: 14px;
        width: 22px;
    }

    .login-icon::after {
        top: 9px;
        left: 7px;
        width: 12px;
        height: 12px;
    }

    .login-icon span::before {
        top: 6px;
        width: 11px;
        box-shadow: 0 16px 0 #ffffff;
    }

    .campus-line {
        display: none;
    }
}
</style>
