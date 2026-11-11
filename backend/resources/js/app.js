/**
 * HRM Plus front-end entry. Every behaviour is opt-in through data attributes
 * (see docs/FRONTEND_GUIDE.md); pages work without JavaScript wherever possible.
 *
 * Page-specific code: create resources/js/pages/<feature>.js exporting an init
 * function, import it here and guard it with a data attribute check.
 */
import { initCheckGroups } from './ui/check-group';
import { initConfirm } from './ui/confirm';
import { initDataTables } from './ui/data-table';
import { initDismiss } from './ui/dismiss';
import { initDropdowns } from './ui/dropdown';
import { initFilters } from './ui/filters';
import { initForms } from './ui/forms';
import { initGlobalSearch } from './ui/global-search';
import { initLocale } from './ui/locale';
import { initModals } from './ui/modal';
import { initPrint } from './ui/print';
import { initSidebar } from './ui/sidebar';
import { initTabs } from './ui/tabs';
import { initTheme } from './ui/theme';
import { initToasts } from './ui/toast';
import { initOrgChart } from './pages/org-chart';
import { initEmployeeForm } from './pages/employee-form';
import { initEmployeeBulk } from './pages/employee-bulk';
import { initPipelineBoard } from './pages/pipeline-board';
import { initScheduleForms } from './pages/schedule-forms';
import { initScheduleCalendar } from './pages/schedule-calendar';
import { initAttendanceBulk } from './pages/attendance-bulk';
import { initLeaveForms, initLeaveReject } from './pages/leave';
import { initPayrollForms } from './pages/payroll';
import { initPayrollRunProgress } from './pages/payroll-run';

export { http, HttpError } from './lib/http';
export { toast } from './ui/toast';
export { confirmDialog } from './ui/confirm';
export { openModal, closeModal } from './ui/modal';
export { showErrors, clearErrors, validateForm } from './ui/forms';

function boot() {
    // Controls that only make sense with JavaScript are rendered hidden + data-js-only.
    document.querySelectorAll('[data-js-only]').forEach((element) => {
        element.hidden = false;
    });

    initTheme();
    initLocale();
    initSidebar();
    initDropdowns();
    initModals();
    initConfirm();
    initToasts();
    initDismiss();
    initTabs();
    initForms();
    initFilters();
    initDataTables();
    initCheckGroups();
    initGlobalSearch();
    initPrint();

    if (document.querySelector('[data-org-chart]')) {
        initOrgChart();
    }

    if (document.querySelector('form[data-employee-form]')) {
        initEmployeeForm();
    }

    if (document.querySelector('[data-bulk-form]')) {
        initEmployeeBulk();
    }

    if (document.querySelector('[data-pipeline-board]')) {
        initPipelineBoard();
    }

    if (document.querySelector('form[data-shift-form], form[data-work-schedule-form], form[data-scope-form], form[data-schedule-change-form]')) {
        initScheduleForms();
    }

    if (document.querySelector('form[data-leave-form]')) {
        initLeaveForms();
    }

    if (document.getElementById('leave-reject-modal')) {
        initLeaveReject();
    }

    if (document.querySelector('[data-schedule-calendar]')) {
        initScheduleCalendar();
    }

    if (document.querySelector('[data-bulk-attendance]')) {
        initAttendanceBulk();
    }

    if (document.querySelector('select[data-switch], [data-repeater]')) {
        initPayrollForms();
    }

    if (document.querySelector('[data-payroll-progress]')) {
        initPayrollRunProgress();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
