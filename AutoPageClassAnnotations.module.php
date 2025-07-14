<?php namespace ProcessWire;

class AutoPageClassAnnotations extends WireData implements Module {
    /**
     * Module information
     */
    public static function getModuleInfo() {
        return [
            'title' => 'Auto Page Class Annotations',
            'summary' =>
                'Automatically adds PHPDoc Annotations for custom page classes when fields or fieldgroups are saved. Heavily based on AutoTemplateStubs by Robin Sallis',
            'version' => '0.0.1',
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
     */
    public $skipFieldtypes = [
        'FieldtypeFieldsetOpen',
        'FieldtypeFieldsetTabOpen',
        'FieldtypeFieldsetGroup',
        'FieldtypeFieldsetClose',
    ];

    /**
     * Skip these system templates
     */
    public $skipTemplates = ['admin', 'form-builder', 'language', 'permission', 'role'];

    public function __construct() {
        parent::__construct();
    }

    public function ready() {
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
                if ($this->custom_page_class_compatible) {
                    $class_name = ucfirst($this->wire()->sanitizer->camelCase($field->name)) . 'Page';
                    return "FieldsetPage|Repeater{$class_name}";
                } else {
                    return "FieldsetPage|{$this->class_prefix}repeater_{$field->name}";
                }
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

    protected function fieldSaved(HookEvent $event) {
        $field = $event->arguments(0);
        if (in_array((string) $field->type, $this->skipFieldtypes)) {
            return;
        }
        foreach ($field->getTemplates() as $template) {
            $this->generatePageClassAnnotation($template);
        }
    }

    protected function templateSaved(HookEvent $event) {
        $template = $event->arguments(0);
        if (in_array($template->name, $this->skipTemplates)) {
            return;
        }
        $this->generatePageClassAnnotation($template);
    }

    protected function fieldContextSaved(HookEvent $event) {
        $fieldgroup = $event->arguments(1);
        foreach ($fieldgroup->getTemplates() as $template) {
            if (in_array($template->name, $this->skipTemplates)) {
                continue;
            }
            $this->generatePageClassAnnotation($template);
        }
    }

    protected function fieldgroupSaved(HookEvent $event) {
        $fieldgroup = $event->arguments(0);
        foreach ($fieldgroup->getTemplates() as $template) {
            if (in_array($template->name, $this->skipTemplates)) {
                continue;
            }
            $this->generatePageClassAnnotation($template);
        }
    }

    protected function getFieldInfo(Field $field, Template $template = null) {
        // If Combo field then create ComboValue class stub for the field
        if ($field instanceof ComboField) {
            /** @var ComboField $field */
            $settings = $field->getComboSettings();
            $phpdoc = $settings->toPhpDoc(false, true);
            $className = "ComboValue_{$field->name}";
            $this->wire()->files->filePutContents(config()->paths()->classes . "$className.php", $phpdoc);
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
            $fieldInfo = $this->getFieldInfo($field, $template);
            $annotations .= "\n * @property {$fieldInfo['returns']} \${$field->name} {$fieldInfo['label']}";
            // Sneaky way to check if the template is of base class RepeaterMatrix:
            // check if it has a field named repeater_matrix_type
            if ($field->name === 'repeater_matrix_type') {
                $isRepeaterMatrixPage = true;
                $className = str_replace('RepeaterPage', 'RepeaterMatrixPage', $className);
            }
        }
        $annotations .= "\n *";

        $filePath = config()->paths()->classes . $className . '.php';
        if (!is_file($filePath) || $this->wire()->files->fileGetContents($filePath) === '') {
            $this->createPageClassFile($className, $template);
        } elseif ($isRepeaterMatrixPage && time() - filectime($filePath <= 3)) {
            // If the pageClass is a RepeaterMatrix and the file was touched in the last three seconds,
            // we assume that it was created and has a wrong page class as we did not know if it was
            // a RepeaterPage or a RepeaterMatrixPage when creating it. We fix this now
            $fileContent = $this->wire()->files->fileGetContents($filePath);
            $fileContent = str_replace(' extends RepeaterPage ', ' extends RepeaterMatrixPage ', $fileContent);
            $this->wire()->files->filePutContents($filePath, $fileContent);
        }

        $fileContent = $this->wire()->files->fileGetContents($filePath);
        $fileContent = $this->setNewAnnotations($fileContent, $annotations);
        $this->wire()->files->filePutContents($filePath, $fileContent);
    }

    protected function createPageClassFile(string $className, Template $template): void {
        $filePath = config()->paths()->classes . $className . '.php';
        $fileContent = "<?php";
        $fileContent.= (!str_contains($className, 'Rockpagebuilder')) ? " namespace ProcessWire;\n\n" : "\n\n";
        $baseClassName = 'Page';
        if ($template->pageClass !== '') {
            $baseClassName = $template->pageClass;
        }
        if (explode('_', $template->name)[0] === 'repeater') {
            $baseClassName = 'RepeaterPage';
        }
        $fileContent .= 'class ' . $className . ' extends ' . $baseClassName . ' {}';
        $this->wire()->files->filePutContents($filePath, $fileContent);
    }

    protected function setNewAnnotations($oldContent, $annotations) {
        // Set wrapper around annotations
        $annotationsWithWrapper =
            '/** @AutoPageClassAnnotations' . "\n" . $annotations . "\n" . ' * @AutoPageClassAnnotations */';
        // Find existing auto annotations wrapper
        $regex = '/\/\*\* @AutoPageClassAnnotations[\s\S]*? \* @AutoPageClassAnnotations \*\//';
        // If not existing, find namespace line instead and append wrapper with annotations
        if (!preg_match($regex, $oldContent)) {
            $regex = '/namespace ProcessWire;\R+/';
            $annotationsWithWrapper = "namespace ProcessWire;\n\n" . $annotationsWithWrapper . "\n\n";
        }
        $newContent = preg_replace($regex, $annotationsWithWrapper, $oldContent, 1);

        return $newContent;
    }

    protected function generateAllPageClassAnnotations() {
        foreach ($this->wire()->templates as $template) {
            if (in_array($template->name, $this->skipTemplates)) {
                continue;
            }
            $this->generatePageClassAnnotation($template);
        }
    }

    public function ___install() {
        $this->generateAllPageClassAnnotations();
    }
}
