<?php namespace ProcessWire;

class AutoPageClassAnnotations extends WireData implements Module {
    /**
     * Module information
     */
    public static function getModuleInfo(): array {
        return [
            'title' => 'Auto Page Class Annotations',
            'summary' =>
                'Automatically adds PHPDoc Annotations for custom page classes when fields or fieldgroups are saved. Heavily based on AutoTemplateStubs by Robin Sallis',
            'version' => '0.2.0',
            'author' => 'Robert Weiss',
            'href' => '',
            'icon' => 'code',
            'singular' => true,
            'autoload' => true,
            'requires' => 'ProcessWire>=3.0.113',
        ];
    }

    /**
     * Skip these fieldtypes because they aren't usable within a template file
     * @var array<string>
     */
    public array $skipFieldtypes = [
        'FieldtypeFieldsetOpen',
        'FieldtypeFieldsetTabOpen',
        'FieldtypeFieldsetGroup',
        'FieldtypeFieldsetClose',
    ];

    /**
     * Skip these system templates
     * @var array<string>
     */
    public array $skipTemplates = ['admin', 'form-builder', 'language', 'permission', 'role'];

    /**
     * Constants for file timing checks and annotation markers
     */
    private const REPEATER_MATRIX_FILE_TIME_THRESHOLD = 3;
    private const ANNOTATION_MARKER = '@AutoPageClassAnnotations';

    /**
     * Regex patterns for annotation insertion
     */
    private const REGEX_EXISTING_ANNOTATIONS = '/\/\*\* @AutoPageClassAnnotations[\s\S]*?\* @AutoPageClassAnnotations\s*\*\//s';
    private const REGEX_PAGE_CLASS = '/^(\s*)((?:abstract\s+|final\s+)?class\s+\w+\s+extends\s+\w*Page.*)/m';

    public function __construct() {
        parent::__construct();
    }

    public function ready(): void {
        $this->addHookAfter('Fields::save', $this, 'fieldSaved');
        $this->addHookAfter('Templates::save', $this, 'templateSaved');
        $this->addHookAfter('Fields::saveFieldgroupContext', $this, 'fieldContextSaved');
        $this->addHookAfter('Fieldgroups::save', $this, 'fieldgroupSaved');
    }

    /**
     * Get array of data types returned by core fieldtypes
     *
     * @return array
     */
    public function ___getReturnTypes() {
        return [
            'FieldtypeCache' => 'array',
            'FieldtypeCheckbox' => 'int',
            'FieldtypeComments' => 'CommentArray',
            'FieldtypeDatetime' => 'int|string',
            'FieldtypeEmail' => 'string',
            'FieldtypeFieldsetPage' => function (Field $field) {
                $class_name = ucfirst($this->wire()->sanitizer->camelCase($field->name)).'Page';

                return "FieldsetPage|Repeater{$class_name}";
            },
            'FieldtypeFile' => function (Field $field) {
                switch ($field->outputFormat) {
                    case FieldtypeFile::outputFormatArray:
                        return 'Pagefiles';
                    case FieldtypeFile::outputFormatSingle:
                        return 'Pagefile|null';
                    case FieldtypeFile::outputFormatString:
                        return 'string';
                    default:
                        // outputFormatAuto
                        return $field->maxFiles == 1 ? 'Pagefile|null' : 'Pagefiles';
                }
            },
            'FieldtypeFloat' => 'float',
            'FieldtypeImage' => function (Field $field) {
                switch ($field->outputFormat) {
                    case FieldtypeImage::outputFormatArray:
                        return 'Pageimages';
                    case FieldtypeImage::outputFormatSingle:
                        return 'Pageimage|null';
                    case FieldtypeImage::outputFormatString:
                        return 'string';
                    default:
                        // outputFormatAuto
                        return $field->maxFiles == 1 ? 'Pageimage|null' : 'Pageimages';
                }
            },
            'FieldtypeInteger' => 'int',
            'FieldtypeDecimal' => 'string',
            'FieldtypeModule' => 'string',
            'FieldtypeOptions' => 'SelectableOptionArray',
            'FieldtypePage' => function (Field $field) {
                switch ($field->derefAsPage) {
                    case FieldtypePage::derefAsPageOrFalse:
                        return 'Page|false';
                    case FieldtypePage::derefAsPageOrNullPage:
                        return 'Page|NullPage';
                    default:
                        // derefAsPageArray
                        return 'PageArray';
                }
            },
            'FieldtypePageTable' => 'PageArray',
            'FieldtypePageTitle' => 'string',
            'FieldtypePageTitleLanguage' => 'string',
            'FieldtypePassword' => 'Password',
            'FieldtypeRepeater' => function (Field $field) {
                $class_name = ucfirst($this->wire()->sanitizer->camelCase($field->name)) . 'Page';
                return "RepeaterPageArray|Repeater{$class_name}[]";
            },
            'FieldtypeRepeaterMatrix' => function (Field $field) {
                $class_name = ucfirst($this->wire()->sanitizer->camelCase($field->name)) . 'Page';
                return "RepeaterMatrixPageArray|Repeater{$class_name}[]";
            },
            'FieldtypeTable' => 'TableRows',
            'FieldtypeSelector' => 'string',
            'FieldtypeText' => 'string',
            'FieldtypeTextLanguage' => 'string',
            'FieldtypeTextarea' => 'string',
            'FieldtypeTextareaLanguage' => 'string',
            'FieldtypeCombo' => function (Field $field) {
                return "ComboValue_{$field->name}";
            },
        ];
    }

    protected function fieldSaved(HookEvent $event): void {
        $field = $event->arguments(0);
        if (in_array((string) $field->type, $this->skipFieldtypes)) {
            return;
        }
        foreach ($field->getTemplates() as $template) {
            $this->generatePageClassAnnotation($template);
        }
    }

    protected function templateSaved(HookEvent $event): void {
        $template = $event->arguments(0);
        if (in_array($template->name, $this->skipTemplates)) {
            return;
        }
        $this->generatePageClassAnnotation($template);
    }

    protected function fieldContextSaved(HookEvent $event): void {
        $fieldgroup = $event->arguments(1);
        foreach ($fieldgroup->getTemplates() as $template) {
            if (in_array($template->name, $this->skipTemplates)) {
                continue;
            }
            $this->generatePageClassAnnotation($template);
        }
    }

    protected function fieldgroupSaved(HookEvent $event): void {
        $fieldgroup = $event->arguments(0);
        foreach ($fieldgroup->getTemplates() as $template) {
            if (in_array($template->name, $this->skipTemplates)) {
                continue;
            }
            $this->generatePageClassAnnotation($template);
        }
    }

    /**
     * Build annotation information for a field
     * @param Field         $field    The field to analyze
     * @param Template|null $template Template context for field
     * @return array{label: string, returns: string} Field annotation data
     */
    protected function buildFieldAnnotation(Field $field, ?Template $template = null): array {
        // If Combo field then create ComboValue class stub for the field
        if (class_exists('ComboField') && $field instanceof ComboField) {
            $settings = $field->getComboSettings();
            $phpdoc = $settings->toPhpDoc(false, true);
            $className = "ComboValue_{$field->name}";
            $this->wire()->files->filePutContents($this->wire()->config->paths()->classes."$className.php", $phpdoc);
        }

        if ($template) {
            $field = $template->fieldgroup->getFieldContext($field);
        }

        $fieldType = (string) $field->type;
        $returnTypes = $this->getReturnTypes();
        $returnType = 'mixed'; // default
        if (!empty($returnTypes[$fieldType])) {
            $returnType = $returnTypes[$fieldType];
        }
        if (is_callable($returnType)) {
            $returnType = $returnType($field);
        }
        return [
            'label' => $field->label,
            'returns' => $returnType,
        ];
    }

    protected function generatePageClassAnnotation(Template $template): void {
        $classNameSuffix = 'Page';
        $className = $template->name;
        if (str_starts_with($template->name, 'repeater')) {
            $classNameSuffix = 'RepeaterPage';
            $className = str_replace('repeater_', '', $className);
        }
        $className = ucfirst($this->wire()->sanitizer->camelCase($className)) . $classNameSuffix;

        $annotations = " * \n";
        $templateName = $template->name;
        if ($template->label) {
            $templateName .= " ($template->label)";
        }
        $annotations .= ' * Template: ' . $templateName;
        $isRepeaterMatrixPage = false;
        foreach ($template->fields as $field) {
            if (in_array((string) $field->type, $this->skipFieldtypes)) {
                continue;
            }
            $fieldInfo = $this->buildFieldAnnotation($field, $template);
            $annotations .= "\n * @property {$fieldInfo['returns']} \${$field->name} {$fieldInfo['label']}";
            // Sneaky way to check if the template is of base class RepeaterMatrix:
            // check if it has a field named repeater_matrix_type
            if ($field->name === 'repeater_matrix_type') {
                $isRepeaterMatrixPage = true;
                $className = str_replace('RepeaterPage', 'RepeaterMatrixPage', $className);
            }
        }
        $annotations .= "\n *";

        $filePath = $this->wire()->config->paths()->classes.$className.'.php';
        if (!is_file($filePath) || $this->wire()->files->fileGetContents($filePath) === '') {
            $this->createPageClassFile($className, $template);
        } elseif ($isRepeaterMatrixPage && is_file($filePath) && time() - filectime($filePath) <= self::REPEATER_MATRIX_FILE_TIME_THRESHOLD) {
            // If the pageClass is a RepeaterMatrix and the file was touched in the last three seconds,
            // we assume that it was created and has a wrong page class as we did not know if it was
            // a RepeaterPage or a RepeaterMatrixPage when creating it. We fix this now
            $fileContent = $this->wire()->files->fileGetContents($filePath);
            if ($fileContent !== false) {
                $fileContent = str_replace(' extends RepeaterPage ', ' extends RepeaterMatrixPage ', $fileContent);
                $this->wire()->files->filePutContents($filePath, $fileContent);
            }
        }

        $fileContent = $this->wire()->files->fileGetContents($filePath);
        if ($fileContent !== false) {
            $updatedContent = $this->insertOrUpdateAnnotations($fileContent, $annotations);
            $this->wire()->files->filePutContents($filePath, $updatedContent);
        }
    }

    protected function createPageClassFile(string $className, Template $template): void {
        $filePath = $this->wire()->config->paths()->classes.$className.'.php';

        // Validate className is not empty
        if (empty($className)) {
            return;
        }

        $fileContent = "<?php ";
        $useStrictPreamble = (bool)($this->wire()->config->AutoPageClassAnnotationsStrictPreamble ?? false);
        if ($useStrictPreamble) {
            $fileContent .= "declare(strict_types=1);\n\n";
        }

        // Only add namespace if not Rockpagebuilder class
        $fileContent .= (!str_contains($className, 'Rockpagebuilder')) ? "namespace ProcessWire;\n\n" : "\n\n";

        // Set classname this class is extending on
        $baseClassName = 'Page';
        if (!empty($template->pageClass)) {
            $baseClassName = $template->pageClass;
        }
        if (explode('_', $template->name)[0] === 'repeater') {
            $baseClassName = 'RepeaterPage';
        }

        // Class declaration line
        $fileContent .= 'class ' . $className . ' extends ' . $baseClassName . ' {}';
        $this->wire()->files->filePutContents($filePath, $fileContent);
    }

    /**
     * Replace or insert PHPDoc annotations in page class file content
     * @param string $oldContent  Original file content
     * @param string $annotations Annotation content to insert
     * @return string Modified file content
     */
    protected function insertOrUpdateAnnotations(string $oldContent, string $annotations): string {
        $annotationsWithWrapper = $this->buildAnnotationWrapper($annotations);

        // First, try to replace existing annotations
        if ($this->hasExistingAnnotations($oldContent)) {
            return $this->replaceExistingAnnotations($oldContent, $annotationsWithWrapper);
        }

        // No existing annotations found, insert new ones at appropriate location
        return $this->insertNewAnnotations($oldContent, $annotationsWithWrapper);
    }

    /**
     * Build the complete annotation wrapper with markers
     * @param string $annotations The annotation content
     * @return string Complete annotation block
     */
    private function buildAnnotationWrapper(string $annotations): string {
        return '/** '.self::ANNOTATION_MARKER."\n".$annotations."\n".' * '.self::ANNOTATION_MARKER.' */';
    }

    /**
     * Check if file content already has existing annotations
     * @param string $content File content to check
     * @return bool True if annotations exist
     */
    private function hasExistingAnnotations(string $content): bool {
        return preg_match(self::REGEX_EXISTING_ANNOTATIONS, $content) === 1;
    }

    /**
     * Replace existing annotation block with new content
     * @param string $content                Original content
     * @param string $annotationsWithWrapper New annotation block
     * @return string Updated content
     */
    private function replaceExistingAnnotations(string $content, string $annotationsWithWrapper): string {
        return preg_replace(self::REGEX_EXISTING_ANNOTATIONS, $annotationsWithWrapper, $content, 1) ?? $content;
    }

    /**
     * Insert new annotations immediately before the Page class declaration
     * @param string $content                Original file content  
     * @param string $annotationsWithWrapper Annotation block to insert
     * @return string Updated content with annotations, or original content if no Page class found
     */
    private function insertNewAnnotations(string $content, string $annotationsWithWrapper): string {
        // Find class extending *Page and insert annotations immediately before it
        // Matches: (optional abstract/final) class SomeName extends SomePage/Page { 
        if (preg_match(self::REGEX_PAGE_CLASS, $content)) {
            $replacement = '$1' . $annotationsWithWrapper . "\n\n" . '$1$2';
            return preg_replace(self::REGEX_PAGE_CLASS, $replacement, $content, 1) ?? $content;
        }
        
        // If no Page class found, return original content unchanged
        return $content;
    }

    protected function generateAllPageClassAnnotations(): void {
        foreach ($this->wire()->templates as $template) {
            if (in_array($template->name, $this->skipTemplates)) {
                continue;
            }
            $this->generatePageClassAnnotation($template);
        }
    }

    public function ___install(): void {
        $this->generateAllPageClassAnnotations();
    }
}
