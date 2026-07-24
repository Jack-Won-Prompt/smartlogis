import './bootstrap';
import './lib/interactions';
import './lib/ui';
import './scan';
import './grid/smartgrid';

// Chart.js 공통 테마를 전역으로 노출(대시보드 뷰에서 window.SmartCharts 사용)
import * as SmartCharts from './charts/theme';
window.SmartCharts = SmartCharts;

// Livewire + Alpine 수동 번들 — Alpine 을 하나만 두어 Livewire 내장 Alpine 과의 충돌을 방지한다.
// (모든 페이지에서 동일한 Alpine 인스턴스를 사용)
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;

Livewire.start();
