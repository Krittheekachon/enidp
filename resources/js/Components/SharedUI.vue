<script lang="ts">
import { router } from '@inertiajs/vue3';
import {
    computed,
    defineComponent,
    h as vueH,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
    type CSSProperties,
    type PropType,
} from 'vue';

interface AutoSelectProps {
    label: string;
    value: string;
    onChange: (val: string) => void;
    options: string[];
    placeholder?: string;
    required?: boolean;
    style?: CSSProperties;
}

export const AutoSelect = defineComponent({
    name: 'AutoSelect',
    props: {
        label: {
            type: String,
            required: true,
        },
        value: {
            type: String,
            required: true,
        },
        onChange: {
            type: Function as PropType<AutoSelectProps['onChange']>,
            required: true,
        },
        options: {
            type: Array as PropType<string[]>,
            required: true,
        },
        placeholder: {
            type: String,
            default: '',
        },
        required: {
            type: Boolean,
            default: false,
        },
        style: {
            type: Object as PropType<CSSProperties>,
            default: () => ({}),
        },
    },
    setup(props) {
        const inputValue = ref(props.value || '');
        const isOpen = ref(false);
        const containerRef = ref<HTMLDivElement | null>(null);
        const upward = ref(false);

        watch(
            () => props.value,
            (value) => {
                inputValue.value = value || '';
            },
        );

        watch(isOpen, (open) => {
            if (!open || !containerRef.value) return;

            const rect = containerRef.value.getBoundingClientRect();
            upward.value = window.innerHeight - rect.bottom < 250;
        });

        const containerStyle = computed<CSSProperties>(() => ({
            position: 'relative',
            minWidth: '180px',
            ...props.style,
        }));

        const arrowStyle = computed<CSSProperties>(() => ({
            position: 'absolute',
            right: '10px',
            top: '50%',
            transform: `translateY(-50%) ${isOpen.value ? 'rotate(180deg)' : ''}`,
            pointerEvents: 'none',
            color: 'var(--text3)',
            display: 'flex',
            transition: 'transform 0.2s',
        }));

        const dropdownStyle = computed<CSSProperties>(() => ({
            position: 'absolute',
            bottom: upward.value ? 'calc(100% + 4px)' : 'auto',
            top: upward.value ? 'auto' : 'calc(100% + 4px)',
            left: 0,
            right: 0,
            zIndex: 1000,
            background: '#fff',
            border: '1px solid var(--border)',
            borderRadius: 'var(--r)',
            boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
            maxHeight: '260px',
            overflowY: 'auto',
            animation: upward.value ? 'slideUp 0.1s ease-out' : 'slideDown 0.1s ease-out',
        }));

        const filtered = computed(() =>
            props.options.filter((opt) =>
                opt.toLowerCase().includes(inputValue.value.toLowerCase()),
            ),
        );

        const optionStyle = (opt: string): CSSProperties => ({
            padding: '12px 14px',
            cursor: 'pointer',
            fontSize: '13px',
            borderBottom: '1px solid #f8fafc',
            background: opt === props.value ? 'var(--blue-lt)' : 'transparent',
            color: opt === props.value ? 'var(--blue)' : 'var(--text1)',
            fontWeight: opt === props.value ? 600 : 400,
            transition: 'background 0.2s',
        });

        const handleInput = (event: Event) => {
            const target = event.target as HTMLInputElement;
            inputValue.value = target.value;
            isOpen.value = true;
        };

        const selectOption = (event: MouseEvent, opt: string) => {
            event.preventDefault();
            props.onChange(opt);
            inputValue.value = opt;
            isOpen.value = false;
        };

        const handleMouseEnter = (event: MouseEvent, opt: string) => {
            if (opt !== props.value) {
                (event.currentTarget as HTMLElement).style.background = '#f1f5f9';
            }
        };

        const handleMouseLeave = (event: MouseEvent, opt: string) => {
            if (opt !== props.value) {
                (event.currentTarget as HTMLElement).style.background = 'transparent';
            }
        };

        const closeDropdown = () => {
            isOpen.value = false;
            inputValue.value = props.value || '';
        };

        return {
            inputValue,
            isOpen,
            containerRef,
            containerStyle,
            arrowStyle,
            dropdownStyle,
            filtered,
            optionStyle,
            handleInput,
            selectOption,
            handleMouseEnter,
            handleMouseLeave,
            closeDropdown,
        };
    },
    template: `
        <div ref="containerRef" class="fg" :style="containerStyle">
            <label class="lbl">{{ label }}</label>
            <div style="position: relative">
                <input
                    class="inp"
                    :value="inputValue"
                    :placeholder="placeholder"
                    :required="required"
                    autocomplete="off"
                    @input="handleInput"
                    @focus="isOpen = true"
                />
                <div :style="arrowStyle">
                    <svg width="16" height="16" fill="none" viewBox="0 0 20 20">
                        <path
                            stroke="currentColor"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="m6 8 4 4 4-4"
                        />
                    </svg>
                </div>
            </div>

            <template v-if="isOpen">
                <div :style="dropdownStyle">
                    <template v-if="filtered.length > 0">
                        <div
                            v-for="opt in filtered"
                            :key="opt"
                            :style="optionStyle(opt)"
                            @mousedown="selectOption($event, opt)"
                            @mouseenter="handleMouseEnter($event, opt)"
                            @mouseleave="handleMouseLeave($event, opt)"
                        >
                            {{ opt }}
                        </div>
                    </template>
                    <div v-else class="empty-option">
                        ไม่พบข้อมูล
                    </div>
                </div>
                <div class="dropdown-backdrop" @mousedown="closeDropdown" />
            </template>
        </div>
    `,
});

export const ExcelImportModal = defineComponent({
    name: 'ExcelImportModal',
    props: {
        title: {
            type: String,
            required: true,
        },
        templateName: {
            type: String,
            required: true,
        },
        templateFile: {
            type: String,
            default: '',
        },
        onClose: {
            type: Function as PropType<() => void>,
            required: true,
        },
        importUrl: {
            type: String,
            default: '',
        },
        onImported: {
            type: Function as PropType<(page: any) => void>,
            default: null,
        },
    },
    setup(props) {
        const selectedFile = ref<File | null>(null);
        const fileInputRef = ref<HTMLInputElement | null>(null);
        const isDragging = ref(false);
        const fileError = ref('');

        const importButtonStyle = computed<CSSProperties>(() => ({
            minWidth: '140px',
            borderRadius: 'var(--r)',
            opacity: 1,
            cursor: selectedFile.value ? 'pointer' : 'not-allowed',
        }));

        const selectedFileSize = computed(() => {
            if (!selectedFile.value) return '';

            return `ขนาดไฟล์: ${(selectedFile.value.size / 1024).toFixed(2)} KB`;
        });

        const downloadFileName = computed(() => {
            const lower = props.templateName.toLowerCase();
            if (lower.includes('user')) return 'User_Template.xlsx';
            if (lower.includes('competency')) return 'Competency_Template.xlsx';

            return props.templateName;
        });

        const downloadTemplate = () => {
            const link = document.createElement('a');
            link.href = props.templateFile || `/templates/${downloadFileName.value}`;
            link.download = downloadFileName.value;
            link.click();
        };

        const setSelectedFile = (file?: File) => {
            fileError.value = '';

            if (!file) return;

            const allowedExtensions = ['.xlsx', '.xls', '.csv'];
            const lowerName = file.name.toLowerCase();
            const isAllowed = allowedExtensions.some((ext) => lowerName.endsWith(ext));

            if (!isAllowed) {
                selectedFile.value = null;
                fileError.value = 'รองรับเฉพาะไฟล์ .xlsx, .xls หรือ .csv';
                return;
            }

            selectedFile.value = file;
        };

        const fileFromDataTransfer = (dataTransfer: DataTransfer | null): File | undefined => {
            if (!dataTransfer) return undefined;
            if (dataTransfer.files?.length) return dataTransfer.files[0];

            const fileItem = Array.from(dataTransfer.items || []).find((item) => item.kind === 'file');
            return fileItem?.getAsFile() || undefined;
        };

        const handleFileChange = (event: Event) => {
            const target = event.target as HTMLInputElement;
            setSelectedFile(target.files?.[0]);
        };

        const handleDragEnter = (event: DragEvent) => {
            event.preventDefault();
            isDragging.value = true;
        };

        const handleDragOver = (event: DragEvent) => {
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }
            isDragging.value = true;
        };

        const handleDragLeave = (event: DragEvent) => {
            event.preventDefault();
            const target = event.currentTarget as HTMLElement;
            const related = event.relatedTarget as Node | null;

            if (!related || !target.contains(related)) {
                isDragging.value = false;
            }
        };

        const handleDrop = (event: DragEvent) => {
            event.preventDefault();
            event.stopPropagation();
            isDragging.value = false;
            const file = fileFromDataTransfer(event.dataTransfer);

            if (!file) {
                fileError.value = 'ลากไฟล์จาก Finder หรือกดคลิกเพื่อเลือกไฟล์จากเครื่อง';
                return;
            }

            setSelectedFile(file);
        };

        const openFileDialog = () => {
            fileInputRef.value?.click();
        };

        const handleImport = () => {
            if (!selectedFile.value) return;

            if (!props.importUrl) {
                alert(`นำเข้าไฟล์ "${selectedFile.value.name}" เรียบร้อยแล้ว! (Mock Import)`);
                props.onClose();
                return;
            }

            router.post(props.importUrl, { file: selectedFile.value }, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    props.onImported?.(page);
                    props.onClose();
                },
                onError: (errors) => {
                    const firstError = Object.values(errors || {})[0];
                    fileError.value = String(firstError || 'ไม่สามารถนำเข้าไฟล์นี้ได้');
                },
            });
        };

        const preventWindowDrop = (event: DragEvent) => {
            event.preventDefault();
        };

        onMounted(() => {
            window.addEventListener('dragover', preventWindowDrop);
            window.addEventListener('drop', preventWindowDrop);
        });

        onBeforeUnmount(() => {
            window.removeEventListener('dragover', preventWindowDrop);
            window.removeEventListener('drop', preventWindowDrop);
        });

        return () =>
            vueH('div', { class: 'mo shared-import-modal' }, [
                vueH('div', { class: 'mo-box anim-fade-in import-box' }, [
                    vueH('div', { class: 'mo-h import-header' }, [
                        vueH('div', [
                            vueH('div', { class: 'fw8 fs18 import-title' }, props.title),
                            vueH('div', { class: 'fs12 muted mt4' }, 'อัปโหลดไฟล์ Excel เพื่อนำเข้าข้อมูลเข้าสู่ระบบโดยตรง'),
                        ]),
                        vueH('button', {
                            class: 'btn-close',
                            type: 'button',
                            'aria-label': 'ปิด',
                            onClick: props.onClose,
                        }, '×'),
                    ]),

                    vueH('div', { class: 'mo-b import-body' }, [
                        vueH('div', { class: 'template-card' }, [
                            vueH('div', { class: 'template-copy' }, [
                                vueH('div', { class: 'template-icon' }, ''),
                                vueH('div', [
                                    vueH('div', { class: 'fw8 fs14 template-title' }, 'ไฟล์แม่แบบ (Template)'),
                                    vueH('div', { class: 'fs11 muted' }, 'ดาวน์โหลดเพื่อเตรียมข้อมูลให้ถูกต้อง'),
                                ]),
                            ]),
                            vueH('div', { class: 'template-actions' }, [
                                vueH('button', {
                                    class: 'btn btn-s btn-sm flex ic g8 download-button',
                                    type: 'button',
                                    onClick: downloadTemplate,
                                }, [
                                    vueH('span', { class: 'download-icon' }, ''),
                                    'ดาวน์โหลดตาราง',
                                ]),
                                vueH('div', { class: 'template-support' }, 'รองรับไฟล์ .xlsx, .csv'),
                            ]),
                        ]),

                        vueH('div', { class: 'fg' }, [
                            vueH('label', { class: 'lbl import-file-label' }, 'เลือกไฟล์จากคอมพิวเตอร์ของคุณ'),
                            vueH('input', {
                                ref: (el) => {
                                    fileInputRef.value = el as HTMLInputElement | null;
                                },
                                type: 'file',
                                class: 'hidden-file-input',
                                accept: '.xlsx, .xls, .csv',
                                onChange: handleFileChange,
                            }),
                            vueH('div', {
                                class: ['upload-dropzone', isDragging.value ? 'is-dragging' : '', fileError.value ? 'has-error' : ''],
                                onClick: openFileDialog,
                                onDragEnter: handleDragEnter,
                                onDragOver: handleDragOver,
                                onDragLeave: handleDragLeave,
                                onDrop: handleDrop,
                            }, [
                                vueH('div', { class: 'upload-content' }, [
                                    vueH('div', { class: 'upload-icon-pulse' }, selectedFile.value ? '' : ''),
                                    vueH('div', { class: 'upload-title' }, selectedFile.value
                                        ? selectedFile.value.name
                                        : isDragging.value
                                            ? 'ปล่อยไฟล์ตรงนี้ได้เลย'
                                            : 'ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อค้นหา'),
                                    vueH('div', { class: 'upload-desc' }, selectedFile.value
                                        ? selectedFileSize.value
                                        : 'ระบบจะตรวจสอบหัวข้อในตารางอัตโนมัติ กรุณาตรวจสอบให้แน่ใจว่าไม่มีเซลล์ที่ว่างเปล่าในคอลัมน์ที่จำเป็น'),
                                    fileError.value
                                        ? vueH('div', { class: 'upload-error' }, fileError.value)
                                        : null,
                                ]),
                            ]),
                        ]),

                        vueH('div', { class: 'flex g12 mt32 import-actions' }, [
                            vueH('button', {
                                class: 'btn btn-s',
                                type: 'button',
                                style: 'min-width: 100px; border-radius: var(--r)',
                                onClick: props.onClose,
                            }, 'ยกเลิก'),
                            vueH('button', {
                                class: 'btn import-submit-button',
                                type: 'button',
                                disabled: !selectedFile.value,
                                style: importButtonStyle.value,
                                onClick: handleImport,
                            }, 'เริ่มนำเข้าข้อมูล'),
                        ]),
                    ]),
                ]),
            ]);
    },
});

export default defineComponent({
    name: 'SharedUI',
});
</script>

<style>
.dropdown-backdrop {
    position: fixed;
    inset: 0;
    z-index: 900;
}

.empty-option {
    padding: 14px;
    color: var(--color-text-muted);
    font-size: 13px;
    text-align: center;
}

.shared-import-modal {
    z-index: 1000;
}

.import-box {
    width: min(688px, calc(100vw - 30px));
    border-radius: 28px;
    overflow: hidden;
}

.import-header {
    padding: 30px 34px;
    border-bottom: 1px solid #f1f5f9;
}

.import-title {
    color: var(--navy);
}

.import-body {
    padding: 34px;
}

.template-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 18px;
    align-items: start;
    margin-bottom: 4px;
    padding: 0;
    background: #f8fafc;
    border: 1px solid #dbeafe;
    border-radius: 12px;
}

.template-copy {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 52px;
    padding-left: 8px;
}

.template-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    font-size: 20px;
}

.template-title {
    color: var(--color-text-secondary);
    font-size: 16px;
}

.template-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
}

.download-button {
    min-width: 190px;
    min-height: 54px;
    justify-content: center;
    padding: 10px 18px;
    border: 1.5px solid var(--color-primary);
    border-radius: 12px;
    background: #fff;
    color: var(--color-primary);
    font-weight: 800;
}

.download-icon {
    font-size: 16px;
}

.template-support {
    padding-right: 4px;
    color: var(--color-text-muted);
    font-size: 12px;
    font-weight: 700;
}

.import-file-label {
    margin: 0 0 16px;
    color: var(--color-text-secondary);
    font-size: 14px;
}

.hidden-file-input {
    display: none;
}

.upload-content {
    display: grid;
    grid-template-columns: 86px minmax(0, 1fr);
    column-gap: 18px;
    row-gap: 8px;
    align-items: center;
    min-height: 270px;
    padding: 44px 62px;
}

.upload-title {
    min-width: 0;
    color: var(--color-text);
    font-size: 18px;
    font-weight: 900;
    line-height: 1.35;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.upload-desc {
    grid-column: 2;
    min-width: 0;
    color: var(--color-text-muted);
    font-size: 13px;
    font-weight: 700;
    line-height: 1.6;
}

.import-actions {
    justify-content: flex-end;
    margin-top: 20px;
}

.btn-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 50%;
    background: #f1f5f9;
    color: var(--text3);
    cursor: pointer;
    transition: 0.2s;
    font-size: 28px;
    font-weight: 300;
    line-height: 1;
}

.btn-close:hover {
    background: #fee2e2;
    color: #ef4444;
    transform: rotate(90deg);
}

.upload-dropzone {
    border: 2px dashed #dbe3ef;
    border-radius: 22px;
    transition: 0.3s;
    cursor: pointer;
    background: #fafafa;
}

.upload-dropzone:hover {
    border-color: var(--blue);
    background: var(--blue-lt);
}

.upload-dropzone.is-dragging {
    border-color: var(--color-primary);
    background: var(--color-primary-soft);
    box-shadow: inset 0 0 0 2px rgba(117, 41, 45, 0.16);
}

.upload-dropzone.has-error {
    border-color: #ef4444;
    background: #fff5f5;
}

.upload-error {
    grid-column: 2;
    color: #dc2626;
    font-size: 12px;
    font-weight: 800;
}

.upload-icon-pulse {
    font-size: 56px;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.1));
    animation: pulse 2s infinite;
}

.import-actions .btn {
    min-width: 124px;
    min-height: 44px;
    justify-content: center;
    border-radius: 6px !important;
    font-size: 15px;
    font-weight: 800;
}

.import-submit-button {
    border: 1px solid var(--color-primary);
    background: var(--color-primary);
    color: #fff;
    box-shadow: 0 10px 22px rgba(117, 41, 45, 0.18);
}

.import-submit-button:hover:not(:disabled) {
    border-color: var(--color-primary-hover);
    background: var(--color-primary-hover);
    transform: translateY(-1px);
}

.import-submit-button:disabled {
    border-color: #dbe3ef;
    background: #f1f5f9;
    color: #64748b;
    box-shadow: none;
    opacity: 1;
    cursor: not-allowed;
}

@media (max-width: 640px) {
    .import-header,
    .import-body {
        padding: 22px;
    }

    .template-card,
    .upload-content {
        grid-template-columns: 1fr;
    }

    .template-actions {
        align-items: stretch;
    }

    .download-button {
        width: 100%;
    }

    .upload-content {
        min-height: 260px;
        padding: 34px 22px;
        text-align: center;
    }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 0.8;
    }
    50% {
        transform: scale(1.1);
        opacity: 1;
    }
    100% {
        transform: scale(1);
        opacity: 0.8;
    }
}
</style>
