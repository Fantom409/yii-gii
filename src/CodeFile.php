use Yiisoft\Yii\Gii\Component\DiffRendererHtmlInline;
     *
        $this->path = $this->preparePath($path);
     *
     *
        $new->basePath = $this->preparePath($basePath);

    private function preparePath(string $path): string
    {
        return strtr($path, '/\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
    }